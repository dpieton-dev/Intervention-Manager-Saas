<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'project')]
    private Collection $tickets;

    /**
     * @var Collection<int, ProjectMember>
     */
    #[ORM\OneToMany(targetEntity: ProjectMember::class, mappedBy: 'project')]
    private Collection $members;

    /**
     * @var Collection<int, ProjectRole>
     */
    #[ORM\OneToMany(targetEntity: ProjectRole::class, mappedBy: 'project')]
    private Collection $roles;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        // Statut par défaut du projet
        $this->status = 'active';
        $this->tickets = new ArrayCollection();
        $this->members = new ArrayCollection();
        $this->roles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setProject($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getProject() === $this) {
                $ticket->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProjectMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(ProjectMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setProject($this);
        }

        return $this;
    }

    public function removeMember(ProjectMember $member): static
    {
        if ($this->members->removeElement($member)) {
            // set the owning side to null (unless already changed)
            if ($member->getProject() === $this) {
                $member->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProjectRole>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(ProjectRole $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
            $role->setProject($this);
        }

        return $this;
    }

    public function removeRole(ProjectRole $role): static
    {
        if ($this->roles->removeElement($role)) {
            // set the owning side to null (unless already changed)
            if ($role->getProject() === $this) {
                $role->setProject(null);
            }
        }

        return $this;
    }
}
