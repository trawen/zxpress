-- Subliminal Extacy (press_id=213): ezine_article_categories from AI meta classification
-- Generated: 2026-11-07T15:25:17.214Z
SET NAMES utf8mb4;

-- SE#00 #1 001_how_to_use_subliminal_extacy_disk_mag → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 1;

-- SE#00 #11 011_editorial → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 11;

-- SE#00 #21 021_how_to_contribute_to_subliminal_extacy → 121 Прочее (0.8)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 21;

-- SE#00 #31 031_important → 123 Пресса (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 31;

-- SE#00 #41 041_a_message_to_pd_libraries → 121 Прочее (0.8)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 41;

-- SE#00 #51 051_do_your_friends_like_you → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 51;

-- SE#00 #61 061_are_you_a_speccy_freak → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 61;

-- SE#00 #71 071_d_and_multiface_protection → 51 Защита и взлом ПО (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  51,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 71;

-- SE#00 #81 081_things_to_avoid_saying_when_drunk → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 81;

-- SE#00 #91 091_silly_jokes_time → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 91;

-- SE#00 #101 101_poetry_corner → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 101;

-- SE#00 #111 111_strange_but_true → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 111;

-- SE#00 #121 121_do_you_suffer_from_loss_of_memory → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 121;

-- SE#00 #131 131_have_you_any_friends → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 131;

-- SE#00 #141 141_change_of_plan → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 141;

-- SE#00 #151 151_last_friday_night → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 151;

-- SE#00 #161 161_the_mummy_s_curse → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 161;

-- SE#00 #171 171_what_next → 88 Демосцена (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 171;

-- SE#00 #181 181_advertisments → 118 Объявления и реклама (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  118,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 181;

-- SE#00 #191 191_the_end_is_near → 123 Пресса (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '00'
  AND a.number = 191;

-- SE#01 #1 001_how_to_use_subliminal_extacy_1 → 121 Прочее (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 1;

-- SE#01 #11 011_important → 123 Пресса (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 11;

-- SE#01 #21 021_how_to_contribute_to_subliminal_extacy → 121 Прочее (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 21;

-- SE#01 #31 031_editorial → 123 Пресса (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 31;

-- SE#01 #41 041_subliminal_extacy_cix_phone_services → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 41;

-- SE#01 #51 051_ask_fudge_he_s_always_here_with_a_kindly_ear → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 51;

-- SE#01 #61 061_20_things_to_do_whilst_waiting_for_a_game_to_load → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 61;

-- SE#01 #71 071_20_things_to_do_while_loading_a_game_from_disk → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 71;

-- SE#01 #81 081_how_to_write_a_crap_demo_in_basic → 45 Демостроение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  45,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 81;

-- SE#01 #91 091_a_cure_for_travel_sickness → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 91;

-- SE#01 #101 101_orson_talks_shite_for_your_suffering → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 101;

-- SE#01 #111 111_dear_extacy_3_subliminal_extacy → 154 Читательские письма (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  154,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 111;

-- SE#01 #121 121_split_personalities → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 121;

-- SE#01 #131 131_are_you_sad → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 131;

-- SE#01 #141 141_20_useless_tips → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 141;

-- SE#01 #151 151_amandas_all_new_x_rated_diary → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 151;

-- SE#01 #161 161_fountain_public_domain_software → 94 Обзоры (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  94,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 161;

-- SE#01 #171 171_the_new_improved_grafix_mode → 35 Графика (0.8)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  35,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 171;

-- SE#01 #181 181_my_d_power_pack_is_shagged → 21 Доработки (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  21,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 181;

-- SE#01 #191 191_some_of_the_worlds_dirtiest_jokes → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 191;

-- SE#01 #201 201_how_ecstasy_blows_your_mind → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 201;

-- SE#01 #211 211_glossary_of_english_to_german_motoring_terms_honest → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 211;

-- SE#01 #221 221_bogie_goes_walk_a_bouts → 92 Отчёты (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  92,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 221;

-- SE#01 #231 231_40_things_to_do_with_a_duff_floppy → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 231;

-- SE#01 #241 241_20_questions → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 241;

-- SE#01 #251 251_basics_of_machine_code → 31 Ассемблер для начинающих (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  31,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 251;

-- SE#01 #261 261_oi_arsehole_give_us_ya_best_bytes → 121 Прочее (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 261;

-- SE#01 #271 271_quick_and_cheap_way_to_get_pissed → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 271;

-- SE#01 #281 281_handy_hints_and_lifestyle_tips → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 281;

-- SE#01 #291 291_i_m_an_asshole → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 291;

-- SE#01 #301 301_more_silly_jokes → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 301;

-- SE#01 #311 311_public_domain_shareware_explained → 121 Прочее (0.8)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 311;

-- SE#01 #321 321_theory_shit → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 321;

-- SE#01 #331 331_subliminal_tunes → 34 Музыка, AY (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  34,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 331;

-- SE#01 #341 341_the_final_words_of_wisdom → 123 Пресса (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '01'
  AND a.number = 341;

-- SE#02 #1 001_how_to_use_subliminal_extacy_2 → 121 Прочее (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 1;

-- SE#02 #11 011_how_to_contribute_to_subliminal_extacy → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 11;

-- SE#02 #21 021_important → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 21;

-- SE#02 #31 031_editorial → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 31;

-- SE#02 #41 041_karaoke_anyone → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 41;

-- SE#02 #51 051_bogie_s_got_no_penis → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 51;

-- SE#02 #61 061_b_how_to_write_a_crap_demo_b_part_2 → 45 Демостроение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  45,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 61;

-- SE#02 #71 071_weird_game_corner → 2 Обзор (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  2,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 71;

-- SE#02 #81 081_20_things_to_stick_up_your_fanny → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 81;

-- SE#02 #91 091_amandas_all_new_x_rated_diary → 117 Юмор и развлечение (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 91;

-- SE#02 #101 101_ask_uncle_fudgepacker → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 101;

-- SE#02 #111 111_girlzillas_guide_to_lager → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 111;

-- SE#02 #121 121_spectrum_set_ups → 121 Прочее (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 121;

-- SE#02 #131 131_20_things_to_do_if_your_football_team_is_losing_away → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 131;

-- SE#02 #141 141_the_voice_of_the_people → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 141;

-- SE#02 #151 151_spectrum_support_scene → 96 Сцена (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  96,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 151;

-- SE#02 #161 161_chinese_proverbs → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 161;

-- SE#02 #171 171_stupid_lists → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 171;

-- SE#02 #181 181_i_m_a_bit_silly → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 181;

-- SE#02 #191 191_b_basics_of_machine_code_b_part_2 → 31 Ассемблер для начинающих (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  31,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 191;

-- SE#02 #201 201_b_acb_stereo_b_a_new_sound_experience → 12 Звуковые устройства (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  12,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 201;

-- SE#02 #211 211_how_to_be_lame → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 211;

-- SE#02 #221 221_mother_yolpa_gives_birth → 88 Демосцена (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 221;

-- SE#02 #231 231_playing_with_the_index_registers → 29 Программирование (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  29,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 231;

-- SE#02 #241 241_a_dream → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 241;

-- SE#02 #251 251_what_the_hell_is_the_womo_team → 77 Интервью (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  77,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 251;

-- SE#02 #261 261_english_to_german_household_terms → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 261;

-- SE#02 #271 271_euronews → 88 Демосцена (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 271;

-- SE#02 #281 281_who_to_write_too → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 281;

-- SE#02 #291 291_buy_your_games_from_sintech → 118 Объявления и реклама (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  118,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 291;

-- SE#02 #301 301_free_software_and_magic_carpets → 94 Обзоры (0.8)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  94,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 301;

-- SE#02 #311 311_the_final_words_of_wisdom → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '02'
  AND a.number = 311;

-- SE#03 #1 001_one_more_boring_text_from_yerzmyey → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 1;

-- SE#03 #11 011_23_things_which_you_can_do_if_a_program_crashes → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 11;

-- SE#03 #21 021_again_trdos_turdos_trdos → 135 Aналитика и мнение (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 21;

-- SE#03 #31 031_alternatives_to_lol → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 31;

-- SE#03 #41 041_b_the_spectrum_se_b_andy_owen_interview → 77 Интервью (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  77,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 41;

-- SE#03 #51 051_are_you_a_communist → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 51;

-- SE#03 #61 061_assholes_speak_russian → 135 Aналитика и мнение (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 61;

-- SE#03 #71 071_beginners_guide_to_russian → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 71;

-- SE#03 #81 081_brief_information_about_cc1 → 89 Демопати (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  89,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 81;

-- SE#03 #91 091_can_you_say_dezign → 88 Демосцена (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 91;

-- SE#03 #101 101_cheat_s_guide_to_tr_dos_cracking_an_incomprehensible_techy → 51 Защита и взлом ПО (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  51,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 101;

-- SE#03 #111 111_b_chocasutra_b_a_chocoholics_guide_to_sex → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 111;

-- SE#03 #121 121_circulation_of_warez_on_today_s_scene → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 121;

-- SE#03 #131 131_compatibility_an_open_letter_to_the_russian_scene → 88 Демосцена (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 131;

-- SE#03 #141 141_complex_99_and_the_east_west_divide → 135 Aналитика и мнение (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 141;

-- SE#03 #151 151_project_describe_your_disk_interface → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 151;

-- SE#03 #161 161_devious_and_lethal_cocktails → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 161;

-- SE#03 #171 171_demo_parties_breaking_the_mould → 92 Отчёты (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  92,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 171;

-- SE#03 #181 181_draw_routine_with_bresenham_algorithms → 29 Программирование (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  29,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 181;

-- SE#03 #191 191_drunkenness → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 191;

-- SE#03 #201 201_editorial → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 201;

-- SE#03 #211 211_how_to_become_the_best_pc_swapper_in_the_world_ever → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 211;

-- SE#03 #221 221_how_to_contribute_to_subliminal_extacy → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 221;

-- SE#03 #231 231_how_to_use_subliminal_extacy_and_more → 121 Прочее (0.8)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  121,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 231;

-- SE#03 #241 241_how_demos_are_really_made → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 241;

-- SE#03 #251 251_important → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 251;

-- SE#03 #261 261_independent_films → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 261;

-- SE#03 #271 271_interview_with_k_0s_raww_arse → 77 Интервью (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  77,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 271;

-- SE#03 #281 281_is_c64_better_than_speccy → 103 Сравнения платформ (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  103,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 281;

-- SE#03 #291 291_just_intonation_making_music_come_to_life → 34 Музыка, AY (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  34,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 291;

-- SE#03 #301 301_party_report_zx_party_2000_wroclaw_poland_25_27_august_2000 → 92 Отчёты (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  92,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 301;

-- SE#03 #311 311_questions_that_still_remain_unanswered_well_not_anymore → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 311;

-- SE#03 #321 321_some_stupid_shitty_rubbish_from_yerzmyey → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 321;

-- SE#03 #331 331_squernookle → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 331;

-- SE#03 #341 341_news_from_ukraine_still_enough_haxxors_in_smash → 86 Группы (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  86,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 341;

-- SE#03 #351 351_b_the_art_of_spectrum_coding_b_chapter_i → 29 Программирование (0.8)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  29,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 351;

-- SE#03 #361 361_the_prince_of_4096_bytes_conquers_new_lands → 88 Демосцена (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 361;

-- SE#03 #371 371_the_smallest_article_in_the_world → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 371;

-- SE#03 #381 381_timex_in_portugal → 111 История компьютеров (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  111,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 381;

-- SE#03 #391 391_b_tips_b_old_telephone_directories_make_ideal_personal_addre → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 391;

-- SE#03 #401 401_what_a_shame → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 401;

-- SE#03 #411 411_whopping_great_big_lists_a_discussion → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 411;

-- SE#03 #421 421_your_stars → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 421;

-- SE#03 #431 431_your_horoscopes_by_mystic_bogie → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 431;

-- SE#03 #441 441_zilog_dep_is_ugly_but_i_am_more → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 441;

-- SE#03 #451 451_b_zm_polyhedron_b_speccy_of_the_future → 77 Интервью (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  77,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '03'
  AND a.number = 451;

-- SE#04 #1 01_x_imate_megademo_a_little_bit_old_the_best_hardware_dem → 77 Интервью (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  77,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 1;

-- SE#04 #2 02_re_complex99_and_the_east_west_divide → 88 Демосцена (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 2;

-- SE#04 #3 03_the_joys_oph_being_phat → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 3;

-- SE#04 #4 04_aki_as_factor6_my_weird_setup → 21 Доработки (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  21,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 4;

-- SE#04 #5 05_the_story_of_how_raver_had_to_pay_for_his_deadly_sins_b → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 5;

-- SE#04 #6 06_butthe_fine_detail_and_the_general_look_it_just_was_so → 77 Интервью (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  77,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 6;

-- SE#04 #7 07_make_your_programs_system_friendly → 29 Программирование (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  29,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 7;

-- SE#04 #8 08_various_disk_systems_death_of_our_scene → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 8;

-- SE#04 #9 09_i_like_monkeys → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 9;

-- SE#04 #10 10_forever_2002_report_written_by_aki_as_factor6 → 92 Отчёты (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  92,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 10;

-- SE#04 #11 11_demo_reviews_00 → 94 Обзоры (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  94,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 11;

-- SE#04 #12 12_intro_why → 92 Отчёты (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  92,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 12;

-- SE#04 #13 13_ccool_graphics_review_the_usual_but_important_thing → 94 Обзоры (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  94,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 13;

-- SE#04 #14 14_reader_s_letters → 154 Читательские письма (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  154,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 14;

-- SE#04 #15 15_the_castor_cracking_group_history → 114 История демосцены (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  114,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '04'
  AND a.number = 15;

-- SE#05 #1 01_future_of_the_zx_spectrum_poll_by_ps_part_i → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 1;

-- SE#05 #2 02_future_of_the_zx_spectrum_poll_by_ps_part_ii → 135 Aналитика и мнение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  135,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 2;

-- SE#05 #3 03_youtube_scene_by_introspec → 88 Демосцена (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 3;

-- SE#05 #4 04_here_is_a_summary_of_all_the_zx_spectrum_demoscene_publ → 92 Отчёты (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  92,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 4;

-- SE#05 #5 05_in_color_by_darklite_offence_review_by_introspec → 94 Обзоры (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  94,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 5;

-- SE#05 #6 06_reader_s_letters → 154 Читательские письма (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  154,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 6;

-- SE#05 #7 07_deconstructing_an_illusion_by_lordcoxis → 45 Демостроение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  45,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 7;

-- SE#05 #8 08_speccy_for_the_21st_century_by_samsterman → 10 Аппаратное обеспечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  10,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 8;

-- SE#05 #9 09_k_eao_recently_compiled_a_book_summarizing_the_history → 110 История (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  110,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 9;

-- SE#05 #10 10_schizophrenia_story_by_elph_revised_by_ps → 117 Юмор и развлечение (0.5)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 10;

-- SE#05 #12 12_the_best_hot_cocoa_ever → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 12;

-- SE#05 #13 13_calling_dave_fudgepacker_fountain → 117 Юмор и развлечение (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 13;

-- SE#05 #16 11_big_belly_productions_slim_beginnings → 86 Группы (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  86,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '05'
  AND a.number = 16;

-- SE#06 #1 01_oo_interview_with_dave_fountain_by_laesq → 77 Интервью (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  77,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 1;

-- SE#06 #2 02_dr_acula_review_by_andre_luna_leao → 2 Обзор (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  2,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 2;

-- SE#06 #3 03_the_zx_sizif_512_a_review_by_laesq → 25 Pentagon (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  25,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 3;

-- SE#06 #4 04_important_by_laesq_pdz → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 4;

-- SE#06 #5 05_about_this_issue → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 5;

-- SE#06 #6 06_blumps_by_mike_zeroteam → 44 Игростроение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  44,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 6;

-- SE#06 #7 07_intervals_with_signed_numbers_by_mike_zeroteam → 29 Программирование (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  29,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 7;

-- SE#06 #8 08_don_t_ignore_your_hardware_by_laesq → 27 Разные устройства (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  27,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 8;

-- SE#06 #9 09_overcomposure_by_coarsetone → 94 Обзоры (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  94,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '06'
  AND a.number = 9;

-- SE#07 #1 01_01_important_laesq → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 1;

-- SE#07 #2 02_02_about_this_issue_se_team → 123 Пресса (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  123,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 2;

-- SE#07 #3 03_03_the_long_road_to_forever_laesq → 92 Отчёты (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  92,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 3;

-- SE#07 #4 04_04_iterative_fixed_point_division_baze → 29 Программирование (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  29,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 4;

-- SE#07 #5 05_05_la_quercia_cured_code_mr_spiv → 29 Программирование (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  29,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 5;

-- SE#07 #6 06_06_making_the_megademica_soundtrack_i_gasman → 34 Музыка, AY (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  34,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 6;

-- SE#07 #7 07_07_making_the_megademica_soundtrack_ii_gasman → 34 Музыка, AY (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  34,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 7;

-- SE#07 #8 08_08_forever_party_places_mikezt → 92 Отчёты (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  92,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 8;

-- SE#07 #9 09_09_optimizing_pixel_plotting_on_the_speccy_baze → 32 Оптимизация кода (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  32,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 9;

-- SE#07 #10 10_10_demoscene_report_since_se06_ps → 94 Обзоры (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  94,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 10;

-- SE#07 #11 10_11_how_the_zx_spectrum_created_ai_prof_vfd → 117 Юмор и развлечение (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  117,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 11;

-- SE#07 #12 11_12_fixed_tacts_routines_mikezt → 41 Эффекты (0.95)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  41,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 12;

-- SE#07 #13 12_13_fast_ay_register_writes_on_the_128k_baze → 34 Музыка, AY (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  34,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 13;

-- SE#07 #14 13_14_did_you_know_episode_2_mikezt → 110 История (0.9)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  110,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 14;

-- SE#07 #15 14_15_ans_experimentations_with_z80_mr_spiv → 29 Программирование (1)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  29,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 15;

-- SE#07 #16 15_big_belly_productions_second_helpings → 88 Демосцена (0.85)
INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)
SELECT
  88,
  a.id,
  0
FROM articles a
INNER JOIN issue i ON i.id = a.id_issue
WHERE i.id_press = 213
  AND i.title = '07'
  AND a.number = 16;

-- Recount articles_count for touched categories
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=2) WHERE id=2 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=10) WHERE id=10 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=12) WHERE id=12 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=21) WHERE id=21 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=25) WHERE id=25 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=27) WHERE id=27 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=29) WHERE id=29 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=31) WHERE id=31 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=32) WHERE id=32 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=34) WHERE id=34 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=35) WHERE id=35 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=41) WHERE id=41 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=44) WHERE id=44 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=45) WHERE id=45 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=51) WHERE id=51 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=77) WHERE id=77 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=86) WHERE id=86 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=88) WHERE id=88 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=89) WHERE id=89 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=92) WHERE id=92 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=94) WHERE id=94 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=96) WHERE id=96 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=103) WHERE id=103 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=110) WHERE id=110 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=111) WHERE id=111 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=114) WHERE id=114 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=117) WHERE id=117 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=118) WHERE id=118 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=121) WHERE id=121 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=123) WHERE id=123 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=135) WHERE id=135 LIMIT 1;
UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=154) WHERE id=154 LIMIT 1;
