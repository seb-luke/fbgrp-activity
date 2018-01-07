<?php
/**
 * User: Seb
 * Date: 06-Jan-18
 * Time: 23:33
 */

namespace App\Security;


use App\Security\User\FacebookUserProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;

class FacebookApiAuthenticator implements SimplePreAuthenticatorInterface, AuthenticationFailureHandlerInterface
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param TokenInterface $token
     * @param UserProviderInterface $userProvider
     * @param $providerKey
     * @return PreAuthenticatedToken
     * @throws \Doctrine\ORM\ORMException
     */
    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        if (!$userProvider instanceof FacebookUserProvider) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The user provider must be an instance of ApiKeyUserProvider (%s was given).',
                    get_class($userProvider)
                )
            );
        }

        $fbAuthToken = $token->getCredentials();
        $fbUserId = $userProvider->findUserIdForFacebookToken($fbAuthToken);

        if (!$fbAuthToken) {
            throw new CustomUserMessageAuthenticationException(
                sprintf('Facebook Auth Token is NULL', $fbAuthToken)
            );
        }

        $user = $userProvider->loadUserByUsername($fbUserId);

        return new PreAuthenticatedToken(
            $user,
            $fbAuthToken,
            $providerKey,
            $user->getRoles()
        );
    }

    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return $token instanceof PreAuthenticatedToken && $token->getProviderKey() === $providerKey;
    }

    public function createToken(Request $request, $providerKey)
    {
        $fbAuthToken = $request->getSession()->get("fbAuthTkn");
        if (!$fbAuthToken) {
            throw new BadCredentialsException("Facebook Auth Token not found in this session");
        }

        return new PreAuthenticatedToken('anon.', $fbAuthToken, $providerKey);
    }

    /**
     * This is called when an interactive authentication attempt fails. This is
     * called by authentication listeners inheriting from
     * AbstractAuthenticationListener.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response The response to return, never null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $this->logger->error($exception);
        return new Response(
            // this contains information about *why* authentication failed
            // use it, or return your own message
            sprintf("%s\n%s", strtr($exception->getMessageKey(),
                $exception->getMessageData()),
                $exception->getMessage()),
            401
        );
    }
}