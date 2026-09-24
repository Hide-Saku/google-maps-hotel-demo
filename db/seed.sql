-- 架空のホテルと施設（地図の中心は実在の地域の座標だが、ホテル・施設の名前はすべて架空）
SET NAMES utf8mb4;
INSERT INTO hotels (slug, name, center_lat, center_lng, zoom) VALUES
 ('kyoto',   'サンプルホテル京都（架空）', 34.985800, 135.758800, 15),
 ('sapporo', 'サンプルホテル札幌（架空）', 43.068600, 141.350800, 15),
 ('naha',    'サンプルホテル那覇（架空）', 26.212400, 127.679200, 14);

-- 表示するカテゴリはホテルごとに違う（京都=4種、札幌=3種、那覇=3種）
INSERT INTO hotel_categories (hotel_id, category)
 SELECT h.id, c.category FROM hotels h
 JOIN (SELECT 'restaurant' AS category UNION ALL SELECT 'sight' UNION ALL SELECT 'shop' UNION ALL SELECT 'transport') c
 WHERE h.slug = 'kyoto';
INSERT INTO hotel_categories (hotel_id, category)
 SELECT h.id, c.category FROM hotels h
 JOIN (SELECT 'restaurant' AS category UNION ALL SELECT 'sight' UNION ALL SELECT 'shop') c
 WHERE h.slug = 'sapporo';
INSERT INTO hotel_categories (hotel_id, category)
 SELECT h.id, c.category FROM hotels h
 JOIN (SELECT 'restaurant' AS category UNION ALL SELECT 'sight' UNION ALL SELECT 'transport') c
 WHERE h.slug = 'naha';

INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
 SELECT id, 'manual', NULL, 'さくら食堂（架空）', 'restaurant', 34.987200, 135.759900, '架空市 架空町1-1', '手入力の飲食店（架空）' FROM hotels WHERE slug='kyoto';
INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
 SELECT id, 'manual', NULL, '東の庭園（架空）', 'sight', 34.992500, 135.765000, '架空市 架空町2-2', '手入力の観光スポット（架空）' FROM hotels WHERE slug='kyoto';
INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
 SELECT id, 'manual', NULL, 'みやこ雑貨店（架空）', 'shop', 34.983900, 135.754200, '架空市 架空町3-3', '手入力の店舗（架空）' FROM hotels WHERE slug='kyoto';
INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
 SELECT id, 'manual', NULL, '駅前バスのりば（架空）', 'transport', 34.985000, 135.757500, '架空市 架空駅前', '手入力の交通拠点（架空）' FROM hotels WHERE slug='kyoto';
INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
 SELECT id, 'manual', NULL, '雪あかり食堂（架空）', 'restaurant', 43.070100, 141.352000, '架空市 架空通4-4', '手入力の飲食店（架空）' FROM hotels WHERE slug='sapporo';
INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
 SELECT id, 'manual', NULL, '北の展望台（架空）', 'sight', 43.061500, 141.354400, '架空市 架空丘5-5', '手入力の観光スポット（架空）' FROM hotels WHERE slug='sapporo';
INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
 SELECT id, 'manual', NULL, '海風そば（架空）', 'restaurant', 26.214000, 127.681500, '架空市 架空浜6-6', '手入力の飲食店（架空）' FROM hotels WHERE slug='naha';
INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
 SELECT id, 'manual', NULL, '青の海岸公園（架空）', 'sight', 26.208800, 127.673500, '架空市 架空浜7-7', '手入力の観光スポット（架空）' FROM hotels WHERE slug='naha';
