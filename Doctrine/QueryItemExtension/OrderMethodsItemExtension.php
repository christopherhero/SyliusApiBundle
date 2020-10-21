<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Bundle\ApiBundle\Doctrine\QueryItemExtension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ApiBundle\Context\UserContextInterface;
use Sylius\Bundle\ApiBundle\Serializer\ContextKeys;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/** @experimental */
final class OrderMethodsItemExtension implements QueryItemExtensionInterface
{
    /** @var UserContextInterface */
    private $userContext;

    public function __construct(UserContextInterface $userContext)
    {
        $this->userContext = $userContext;
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        string $operationName = null,
        array $context = []
    ) {
        if (!is_a($resourceClass, OrderInterface::class, true)) {
            return;
        }

        $httpRequestMethodType = $context[ContextKeys::HTTP_REQUEST_METHOD_TYPE];
        if ($httpRequestMethodType === Request::METHOD_GET) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $user = $this->userContext->getUser();

        if ($user === null) {
            $this->applyForVisitor($queryBuilder, $rootAlias, $operationName);

            return;
        }

        if ($user instanceof ShopUserInterface && in_array('ROLE_USER', $user->getRoles(), true)) {
            $this->applyForShopUser($queryBuilder, $rootAlias, $operationName, $user);

            return;
        }

        if (
            $user instanceof AdminUserInterface && in_array('ROLE_API_ACCESS', $user->getRoles(), true)) {
            $this->applyForAdminUser($queryBuilder, $rootAlias, $httpRequestMethodType);

            return;
        }

        throw new AccessDeniedHttpException('Requested method is not allowed.');
    }

    private function applyForVisitor(QueryBuilder $queryBuilder, string $rootAlias, string $operationName): void
    {
        $queryBuilder
            ->leftJoin(sprintf('%s.customer', $rootAlias), 'customer')
            ->leftJoin('customer.user', 'user')
            ->andWhere('user IS NULL')
            ->orWhere(sprintf('%s.customer IS NULL', $rootAlias))
        ;

        if ($operationName !== 'shop_select_payment_method') {
            $this->filterCart($queryBuilder, $rootAlias);
        }
    }

    private function applyForShopUser(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        string $operationName,
        ShopUserInterface $user
    ): void {
        $queryBuilder
            ->andWhere(sprintf('%s.customer = :customer', $rootAlias))
            ->setParameter('customer', $user->getCustomer()->getId())
        ;

        if ($operationName !== 'shop_select_payment_method') {
            $this->filterCart($queryBuilder, $rootAlias);
        }
    }

    private function applyForAdminUser(QueryBuilder $queryBuilder, string $rootAlias, string $httpRequestMethodType): void
    {
        if ($httpRequestMethodType === Request::METHOD_DELETE) {
            $this->filterCart($queryBuilder, $rootAlias);
        }
    }
    private function filterCart(QueryBuilder $queryBuilder, string $rootAlias): void
    {
        $queryBuilder
            ->andWhere(sprintf('%s.state = :state', $rootAlias))
            ->setParameter('state', OrderInterface::STATE_CART)
        ;
    }
}
