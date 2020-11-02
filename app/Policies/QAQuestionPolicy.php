<?php

namespace Zhiyi\Plus\Policies;

use Zhiyi\Plus\Models\User;
use SlimKit\PlusQuestion\Models\Question;
use Illuminate\Auth\Access\HandlesAuthorization;

class QAQuestionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can delete the question.
     *
     * @param  \Zhiyi\Plus\Models\User  $user
     * @param  \SlimKit\PlusQuestion\Models\Question  $question
     * @return mixed
     */
    public function delete(User $user, Question $question)
    {
        if ($question->user_id === $user->id) {
            return true;
        } elseif ($user->ability('[Q&A] Manage Questions')) {
            return true;
        }

        return false;
    }
}
