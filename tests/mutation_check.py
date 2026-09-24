"""わざと壊した版でテストが赤くなることを確かめる（リポジトリのコードには壊すための仕掛けを入れない）。
使い方（プロジェクト直下で）: python tests/mutation_check.py
やること: コードのコピーを作り、1か所ずつ壊し、そのコピーを web_test コンテナにマウントしてテストを流す。
正常版は緑、壊した版はすべて赤になれば合格。終わったら web_test を元のコードに戻す。
"""
import os
import re
import shutil
import subprocess
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ENV = dict(os.environ, MSYS_NO_PATHCONV="1")


def sh(args, env=None, timeout=300):
    return subprocess.run(args, cwd=ROOT, capture_output=True, text=True, encoding="utf-8", errors="replace",
                          env=env or ENV, timeout=timeout)


def run_tests(src_dir):
    env = dict(ENV)
    if src_dir:
        env["SRC_DIR"] = src_dir
    up = sh(["docker", "compose", "--profile", "test", "up", "-d", "--force-recreate", "web_test"], env)
    if up.returncode != 0:
        raise RuntimeError("web_test を起動できません: " + up.stderr[-500:])
    # bootstrap（DB 待ち・テーブル作成）が終わり、Apache が応答するまで待つ
    for _ in range(40):
        r = sh(["docker", "compose", "--profile", "test", "exec", "-T", "web_test", "curl", "-s", "-o", "/dev/null", "-w", "%{http_code}", "http://127.0.0.1/"], env)
        if r.stdout.strip() in ("200", "302"):
            break
        subprocess.run(["python", "-c", "import time; time.sleep(1)"])
    r = sh(["docker", "compose", "--profile", "test", "exec", "-T", "web_test", "php", "tests/run_all.php"], env)
    out = r.stdout + r.stderr
    m = re.search(r"結果: (\d+) 件合格 / (\d+) 件失敗", out)
    failed = re.findall(r"^  FAIL (.+)$", out, flags=re.M)
    return (int(m.group(1)), int(m.group(2)), failed) if m else (0, -1, ["(結果行なし) " + out[-300:]])


def make_copy(name):
    dst = os.path.join(tempfile.gettempdir(), "case15_mut_" + name)
    shutil.rmtree(dst, ignore_errors=True)
    shutil.copytree(ROOT, dst, ignore=shutil.ignore_patterns(".git", "logs", ".env", "docs"))
    return dst.replace("\\", "/")


def mutate(dst, rel, old, new):
    p = os.path.join(dst, rel)
    s = open(p, encoding="utf-8").read()
    if s.count(old) != 1:
        raise RuntimeError("壊す対象が1か所に特定できません: %s / %r (%d)" % (rel, old, s.count(old)))
    open(p, "w", encoding="utf-8", newline="\n").write(s.replace(old, new))


MUTATIONS = [
    ("認可チェックを外す（未ログインでも管理画面に入れる）", "src/Auth.php",
     "        if (!self::check()) {\n            redirect('/admin/login.php');\n        }", "        // (認可チェックを外した)"),
    ("CSRF 検証を外す（トークンなしの POST が通る）", "src/Csrf.php",
     "        if ($have === '' || !hash_equals($have, $sent)) {", "        if (false) {"),
    ("冪等の既存チェックを外す（同じデータを流すと必ず追加しようとする）", "src/Importer.php",
     "$cur = $find->fetch();", "$cur = false;"),
    ("SQL をプレースホルダでなく文字列連結にする（ホテル検索）", "src/Repo.php",
     "        $st = Db::pdo()->prepare('SELECT id, slug, name, center_lat, center_lng, zoom FROM hotels WHERE slug = ?');\n        $st->execute([$slug]);\n        return $st->fetch() ?: null;",
     "        $st = Db::pdo()->query(\"SELECT id, slug, name, center_lat, center_lng, zoom FROM hotels WHERE slug = '$slug'\");\n        return $st->fetch() ?: null;"),
    ("HTML エスケープをやめる（h() がそのまま返す）", "src/helpers.php",
     "return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');", "return (string)$s;"),
    ("ログイン成功時にセッションIDを作り直さない（固定化攻撃）", "src/Auth.php",
     "            session_regenerate_id(true);\n", ""),
    ("地図に出す施設の絞り込み（有効カテゴリ）を外す", "src/Repo.php",
     "                  JOIN hotel_categories hc ON hc.hotel_id = f.hotel_id AND hc.category = f.category\n                 WHERE f.hotel_id = ?';",
     "                  LEFT JOIN hotel_categories hc ON hc.hotel_id = f.hotel_id AND hc.category = f.category\n                 WHERE f.hotel_id = ?';"),
]

if __name__ == "__main__":
    print("== 正常版 ==")
    p, f, names = run_tests(None)
    print("  合格 %d / 失敗 %d  -> %s" % (p, f, "GREEN" if f == 0 else "RED（正常版が赤：先に直す）"))
    if f != 0:
        print("\n".join("    FAIL " + n for n in names))
        sys.exit(1)
    bad = 0
    for i, (label, rel, old, new) in enumerate(MUTATIONS, 1):
        dst = make_copy(str(i))
        mutate(dst, rel, old, new)
        p, f, names = run_tests(dst)
        red = f != 0
        print("== 壊す%d: %s ==\n  合格 %d / 失敗 %d  -> %s" % (i, label, p, f, "RED（検知できた）" if red else "GREEN（検知できず＝テストに穴）"))
        for n in names[:4]:
            print("    FAIL " + n)
        if not red:
            bad += 1
    run_tests(None)  # 元のコードに戻す
    print("\n検知できなかった変異: %d 件" % bad)
    sys.exit(1 if bad else 0)
