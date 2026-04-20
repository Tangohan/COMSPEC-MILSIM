-- Auteur réel des messages « recrutement » sur le portail suivi (/enlistment/suivi/{token})
ALTER TABLE enlistment_candidate_messages
  ADD COLUMN actor_user_id INT UNSIGNED NULL DEFAULT NULL AFTER entry_kind,
  ADD KEY idx_enlistment_candidate_messages_actor (actor_user_id),
  ADD CONSTRAINT fk_enlistment_candidate_messages_actor
    FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE;
