<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PassValidateResource;
use App\Domain\Agency\PassValidationService;
use App\Exception\UnprocessableEntityException;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<PassValidateResource> */
final class PassValidateProvider implements ProviderInterface
{
    public function __construct(
        private PassValidationService $validation,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PassValidateResource
    {
        $ref = (string) ($this->requestStack->getCurrentRequest()?->query->get('ref') ?? '');
        if ('' === trim($ref)) {
            throw new UnprocessableEntityException('Query parameter "ref" is required.');
        }

        $result = $this->validation->validate($ref, throwOnInvalid: $this->validation->isStrict());

        return new PassValidateResource(
            ref: $result['ref'],
            valid: $result['valid'],
            holder: $result['holder'],
            status: $result['status'],
            expiresAt: $result['expiresAt'],
            warning: $result['warning'],
        );
    }
}
