ALTER TABLE `letters`
  ADD UNIQUE KEY `uq_letters_slug_ru` (`slug_ru`),
  ADD UNIQUE KEY `uq_letters_slug_en` (`slug_en`);
