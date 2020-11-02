<?php

namespace Zhiyi\Plus\Policies;

use Zhiyi\Plus\Models\User;
use SlimKit\PlusQuestion\Models\Answer;
use Illuminate\Auth\Access\HandlesAuthorization;

class QAAnswerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can delete the answer.
     *
     * @param  \Zhiyi\Plus\Models\User  $user
     * @param  \SlimKit\PlusQuestion\Models\Answer  $answer
     * @return mixed
     */
    public function delete(User $user, Answer $answer)
    {
        if ($answer->user_id === $user->id) {
            return true;
        } elseif ($user->ability('[Q&A] Manage Answers')) {
            return true;
        }

        return false;
    }
}
