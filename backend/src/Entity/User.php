<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'createdBy')]
    private Collection $tickets;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'assignedTo')]
    private Collection $assignedTickets;

    /**
     * @var Collection<int, ProjectMember>
     */
    #[ORM\OneToMany(targetEntity: ProjectMember::class, mappedBy: 'user')]
    private Collection $projectMemberships;

    /**
     * @var Collection<int, TicketComment>
     */
    #[ORM\OneToMany(targetEntity: TicketComment::class, mappedBy: 'createdBy')]
    private Collection $ticketComments;

    /**
     * @var Collection<int, TicketActivity>
     */
    #[ORM\OneToMany(targetEntity: TicketActivity::class, mappedBy: 'createdBy')]
    private Collection $ticketActivities;

    /**
     * @var Collection<int, TicketAttachment>
     */
    #[ORM\OneToMany(targetEntity: TicketAttachment::class, mappedBy: 'createdBy')]
    private Collection $ticketAttachments;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    /**
     * @var Collection<int, ProjectPresence>
     */
    #[ORM\OneToMany(targetEntity: ProjectPresence::class, mappedBy: 'user')]
    private Collection $projectPresences;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->tickets = new ArrayCollection();
        $this->assignedTickets = new ArrayCollection();
        $this->projectMemberships = new ArrayCollection();
        $this->ticketComments = new ArrayCollection();
        $this->ticketActivities = new ArrayCollection();
        $this->ticketAttachments = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->projectPresences = new ArrayCollection();
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
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
            $ticket->setCreatedBy($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getCreatedBy() === $this) {
                $ticket->setCreatedBy(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getAssignedTickets(): Collection
    {
        return $this->assignedTickets;
    }

    public function addAssignedTicket(Ticket $assignedTicket): static
    {
        if (!$this->assignedTickets->contains($assignedTicket)) {
            $this->assignedTickets->add($assignedTicket);
            $assignedTicket->setAssignedTo($this);
        }

        return $this;
    }

    public function removeAssignedTicket(Ticket $assignedTicket): static
    {
        if ($this->assignedTickets->removeElement($assignedTicket)) {
            // set the owning side to null (unless already changed)
            if ($assignedTicket->getAssignedTo() === $this) {
                $assignedTicket->setAssignedTo(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProjectMember>
     */
    public function getProjectMemberships(): Collection
    {
        return $this->projectMemberships;
    }

    public function addProjectMembership(ProjectMember $projectMembership): static
    {
        if (!$this->projectMemberships->contains($projectMembership)) {
            $this->projectMemberships->add($projectMembership);
            $projectMembership->setUser($this);
        }

        return $this;
    }

    public function removeProjectMembership(ProjectMember $projectMembership): static
    {
        if ($this->projectMemberships->removeElement($projectMembership)) {
            // set the owning side to null (unless already changed)
            if ($projectMembership->getUser() === $this) {
                $projectMembership->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TicketComment>
     */
    public function getTicketComments(): Collection
    {
        return $this->ticketComments;
    }

    public function addTicketComment(TicketComment $ticketComment): static
    {
        if (!$this->ticketComments->contains($ticketComment)) {
            $this->ticketComments->add($ticketComment);
            $ticketComment->setCreatedBy($this);
        }

        return $this;
    }

    public function removeTicketComment(TicketComment $ticketComment): static
    {
        if ($this->ticketComments->removeElement($ticketComment)) {
            // set the owning side to null (unless already changed)
            if ($ticketComment->getCreatedBy() === $this) {
                $ticketComment->setCreatedBy(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TicketActivity>
     */
    public function getTicketActivities(): Collection
    {
        return $this->ticketActivities;
    }

    public function addTicketActivity(TicketActivity $ticketActivity): static
    {
        if (!$this->ticketActivities->contains($ticketActivity)) {
            $this->ticketActivities->add($ticketActivity);
            $ticketActivity->setCreatedBy($this);
        }

        return $this;
    }

    public function removeTicketActivity(TicketActivity $ticketActivity): static
    {
        if ($this->ticketActivities->removeElement($ticketActivity)) {
            // set the owning side to null (unless already changed)
            if ($ticketActivity->getCreatedBy() === $this) {
                $ticketActivity->setCreatedBy(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TicketAttachment>
     */
    public function getTicketAttachments(): Collection
    {
        return $this->ticketAttachments;
    }

    public function addTicketAttachment(TicketAttachment $ticketAttachment): static
    {
        if (!$this->ticketAttachments->contains($ticketAttachment)) {
            $this->ticketAttachments->add($ticketAttachment);
            $ticketAttachment->setCreatedBy($this);
        }

        return $this;
    }

    public function removeTicketAttachment(TicketAttachment $ticketAttachment): static
    {
        if ($this->ticketAttachments->removeElement($ticketAttachment)) {
            // set the owning side to null (unless already changed)
            if ($ticketAttachment->getCreatedBy() === $this) {
                $ticketAttachment->setCreatedBy(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProjectPresence>
     */
    public function getProjectPresences(): Collection
    {
        return $this->projectPresences;
    }

    public function addProjectPresence(ProjectPresence $projectPresence): static
    {
        if (!$this->projectPresences->contains($projectPresence)) {
            $this->projectPresences->add($projectPresence);
            $projectPresence->setUser($this);
        }

        return $this;
    }

    public function removeProjectPresence(ProjectPresence $projectPresence): static
    {
        if ($this->projectPresences->removeElement($projectPresence)) {
            // set the owning side to null (unless already changed)
            if ($projectPresence->getUser() === $this) {
                $projectPresence->setUser(null);
            }
        }

        return $this;
    }
}
