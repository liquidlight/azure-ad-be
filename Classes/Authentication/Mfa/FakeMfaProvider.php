<?php

declare(strict_types=1);

namespace DifferentTechnology\AzureAdBe\Authentication\Mfa;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\Response;

class FakeMfaProvider implements MfaProviderInterface
{
	protected Context $context;

	protected ServerRequestInterface $request;

	public function __construct(Context $context)
	{
		$this->context = $context;
	}

	public function canProcess(ServerRequestInterface $request): bool
	{
		return true;
	}

	public function isActive(MfaProviderPropertyManager $propertyManager): bool
	{
		return (bool)$propertyManager->getProperty('active');
	}

	public function isLocked(MfaProviderPropertyManager $propertyManager): bool
	{
		return false;
	}

	public function handleRequest(
		ServerRequestInterface $request,
		MfaProviderPropertyManager $propertyManager,
		string $type
	): ResponseInterface {
		return new Response();
	}

	public function verify(
		ServerRequestInterface $request,
		MfaProviderPropertyManager $propertyManager
	): bool {
		$properties['attempts'] = 0;
		$properties['lastUsed'] = $this->context->getPropertyFromAspect('date', 'timestamp');

		return $propertyManager->updateProperties($properties);
	}

	public function activate(
		ServerRequestInterface $request,
		MfaProviderPropertyManager $propertyManager
	): bool {
		return $this->update($request, $propertyManager);
	}

	public function unlock(
		ServerRequestInterface $request,
		MfaProviderPropertyManager $propertyManager
	): bool {
		if (
			!$this->isActive($propertyManager)
			|| !$this->isLocked($propertyManager)
		) {
			return false;
		}

		return $propertyManager->updateProperties(['attempts' => 0]);
	}

	public function deactivate(
		ServerRequestInterface $request,
		MfaProviderPropertyManager $propertyManager
	): bool {
		if (!$this->isActive($propertyManager)) {
			return false;
		}

		return $propertyManager->updateProperties(['active' => false]);
	}

	public function update(
		ServerRequestInterface $request,
		MfaProviderPropertyManager $propertyManager
	): bool {
		if (!$this->canProcess($request)) {
			return false;
		}

		$properties = [
			'active' => true,
		];

		return $propertyManager->hasProviderEntry()
			? $propertyManager->updateProperties($properties)
			: $propertyManager->createProviderEntry($properties);
	}
}
