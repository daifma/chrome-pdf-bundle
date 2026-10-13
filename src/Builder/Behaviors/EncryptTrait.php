<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * Encrypts the PDF with AES-256. Requires qpdf.
 *
 * @package Behavior\\Encrypt
 */
trait EncryptTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Set PDF user password.
     *
     * @example userPassword('UserDefinedPassword')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('user_password', restrictTo: 'string'))]
    public function userPassword(#[\SensitiveParameter] string|null $userPassword): static
    {
        if (null === $userPassword) {
            $this->getBodyBag()->unset('userPassword');
        } else {
            $this->getBodyBag()->set('userPassword', $userPassword);
        }

        return $this;
    }

    /**
     * Set PDF owner password.
     *
     * @example ownerPassword('OwnerDefinedPassword')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('owner_password', restrictTo: 'string'))]
    public function ownerPassword(#[\SensitiveParameter] string|null $ownerPassword): static
    {
        if (null === $ownerPassword) {
            $this->getBodyBag()->unset('ownerPassword');
        } else {
            $this->getBodyBag()->set('ownerPassword', $ownerPassword);
        }

        return $this;
    }
}
