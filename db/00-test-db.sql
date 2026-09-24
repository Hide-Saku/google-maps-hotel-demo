-- テスト用のDBを作り、アプリ用ユーザーに権限を与える（初回の初期化時だけ実行される）
CREATE DATABASE IF NOT EXISTS hotel_map_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON hotel_map_test.* TO 'app'@'%';
FLUSH PRIVILEGES;
