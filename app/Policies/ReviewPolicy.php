<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }
    public function viewAny(User $user)
    {// Allow viewing reviews for authenticated users
        return $user !== null;
    }
    public function view(User $user, Review $review)
    {
        // Allow viewing if the user is the owner of the review or an admin
        return $user->id === $review->user_id || $user->role === 'admin';
    }
    public function create(User $user)
    {
        // Allow creating a review for authenticated users
        return $user !== null;
    }
    public function update(User $user,Review  $review)
    {
        // Allow updating if the user is the owner of the review
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review)
    {
        // Allow deletion if the user is an admin
        return $user->role === 'admin';
    }
    public function approve(User $user)
    {
        // Allow approving reviews if the user is an admin
        return $user->role === 'admin';
    }
}
