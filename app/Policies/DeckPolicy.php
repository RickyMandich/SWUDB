<?php

namespace App\Policies;

use App\Models\Deck;
use App\Models\User;

class DeckPolicy
{
    public function update(User $user, Deck $deck): bool
    {
        return $user->id === $deck->user_id || $user->can('decks.manage-any');
    }

    public function delete(User $user, Deck $deck): bool
    {
        return $this->update($user, $deck);
    }

    public function view(?User $user, Deck $deck): bool
    {
        return $deck->is_public
            || ($user !== null && ($user->id === $deck->user_id || $user->can('decks.manage-any')));
    }
}
