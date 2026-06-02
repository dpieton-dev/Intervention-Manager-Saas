<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;

class ProjectSecurityService
{
    /**
     * Vérifie si un utilisateur est membre d’un projet.
     */
    public function isProjectMember(Project $project, User $user): bool
    {
        foreach ($project->getMembers() as $member)
        {
            if ($member->getUser()?->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si un utilisateur possède un rôle précis dans un projet.
     */
    public function hasProjectRole(Project $project, User $user, string $roleCode): bool 
    {
        foreach ($project->getMembers() as $member)
        {
            if ($member->getUser()?->getId() === $user->getId() && $member->getProjectRole()?->getCode() === $roleCode) 
            {
                return true;
            }
        }

        return false;
    }

}