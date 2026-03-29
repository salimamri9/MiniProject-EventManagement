<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new User();
        $user->setUsername('johndoe');
        $user->setEmail('john@example.com');
        
        $this->assertEquals('johndoe', $user->getUsername());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertNotEmpty($user->getId());
    }

    public function testUserRoles(): void
    {
        $user = new User();
        
        // Default role should be ROLE_USER
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testUserIdentifier(): void
    {
        $user = new User();
        $user->setEmail('john@example.com');
        
        // Email is the user identifier
        $this->assertEquals('john@example.com', $user->getUserIdentifier());
    }

    public function testUserPassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_password_here');
        
        $this->assertEquals('hashed_password_here', $user->getPassword());
    }
}
