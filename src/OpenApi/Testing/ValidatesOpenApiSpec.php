<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Testing;

use Bambamboole\Spectacular\OpenApi\Testing\Exceptions\UnsupportedOpenApiSpecFileType;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\Exception\Validation\AddressValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Assert;
use ReflectionProperty;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

trait ValidatesOpenApiSpec
{
    use MakesHttpRequests;

    protected ?ValidatorBuilder $openApiValidatorBuilder = null;

    private ?PsrHttpFactory $psr7Factory = null;

    private bool $skipRequestValidation = false;

    private bool $skipResponseValidation = false;

    /** @var list<string|int> */
    private array $additionalResponseCodesToSkip = [];

    public function getOpenApiValidatorBuilder(): ValidatorBuilder
    {
        if ($this->openApiValidatorBuilder instanceof ValidatorBuilder) {
            return $this->openApiValidatorBuilder;
        }

        $this->openApiValidatorBuilder = match ($this->getSpecFileType()) {
            'json' => (new ValidatorBuilder)->fromJsonFile($this->getOpenApiSpecPath()),
            'yaml' => (new ValidatorBuilder)->fromYamlFile($this->getOpenApiSpecPath()),
            default => throw new UnsupportedOpenApiSpecFileType('Expected a JSON or YAML OpenAPI specification.'),
        };

        return $this->openApiValidatorBuilder;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @return TestResponse<SymfonyResponse>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $kernel = $this->app->make(HttpKernel::class);

        $files = array_merge($files, $this->extractFilesFromDataArray($parameters));

        $symfonyRequest = SymfonyRequest::create(
            $this->prepareUrlForRequest($uri),
            $method,
            $parameters,
            $cookies,
            $files,
            array_replace($this->serverVariables, $server),
            $content,
        );

        $request = $this->createTestRequest($symfonyRequest);
        $address = $this->validateRequest($request);
        $response = $kernel->handle($request);

        if ($this->followRedirects) {
            $response = $this->followRedirects($response);
        }

        $kernel->terminate($request, $response);

        $testResponse = $this->createTestResponse($response, $request);

        $this->validateResponse($address, $testResponse->baseResponse);

        return $testResponse;
    }

    public function withoutValidation(): static
    {
        $this->skipRequestValidation = true;
        $this->skipResponseValidation = true;

        return $this;
    }

    public function withoutRequestValidation(): static
    {
        $this->skipRequestValidation = true;

        return $this;
    }

    public function withoutResponseValidation(): static
    {
        $this->skipResponseValidation = true;

        return $this;
    }

    public function skipResponseCode(string|int ...$responseCodes): static
    {
        $this->additionalResponseCodesToSkip = [
            ...$this->additionalResponseCodesToSkip,
            ...$responseCodes,
        ];

        return $this;
    }

    protected function getResponseCodesToSkipRegex(): string
    {
        return '/'.implode('|', array_map(
            fn (string|int $code): string => "({$code})",
            [
                ...$this->configuredResponseCodesToSkip(),
                ...$this->additionalResponseCodesToSkip,
            ],
        )).'/';
    }

    protected function shouldSkipResponseValidation(SymfonyResponse $response): bool
    {
        if ($this->skipResponseValidation) {
            $this->skipResponseValidation = false;

            return true;
        }

        return preg_match($this->getResponseCodesToSkipRegex(), (string) $response->getStatusCode()) === 1;
    }

    protected function shouldSkipRequestValidation(): bool
    {
        if ($this->skipRequestValidation) {
            $this->skipRequestValidation = false;

            return true;
        }

        return false;
    }

    protected function getOpenApiSpecPath(): string
    {
        return config('spectacular.openapi.validation.path') ?: base_path('openapi.json');
    }

    protected function getSpecFileType(): string
    {
        $type = strtolower(pathinfo($this->getOpenApiSpecPath(), PATHINFO_EXTENSION));

        if (! in_array($type, ['json', 'yaml'], true)) {
            throw new UnsupportedOpenApiSpecFileType("Expected a JSON or YAML OpenAPI specification, got {$type}.");
        }

        return $type;
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        if ($request->headers->has('Authorization')) {
            return $request;
        }

        $authenticatedRequest = clone $request;
        $authenticatedRequest->headers->set('Authorization', 'Bearer token');

        return $authenticatedRequest;
    }

    protected function validateRequest(SymfonyRequest $request): OperationAddress
    {
        if ($this->shouldSkipRequestValidation()) {
            $psr7Request = $this->getPsr7Factory()->createRequest($request);

            return new OperationAddress($psr7Request->getUri()->getPath(), strtolower($request->getMethod()));
        }

        try {
            return $this->getOpenApiValidatorBuilder()
                ->getRequestValidator()
                ->validate($this->getPsr7Factory()->createRequest($this->getAuthenticatedRequest($request)));
        } catch (AddressValidationFailed $exception) {
            $this->failValidation($exception, $request->getContent());
        }
    }

    protected function validateResponse(OperationAddress $address, SymfonyResponse $response): void
    {
        if ($this->shouldSkipResponseValidation($response)) {
            return;
        }

        try {
            $this->getOpenApiValidatorBuilder()
                ->getResponseValidator()
                ->validate($address, $this->getPsr7Factory()->createResponse($response));
        } catch (AddressValidationFailed $exception) {
            $this->failValidation($exception, $response->getContent());
        }
    }

    /** @return list<string|int> */
    private function configuredResponseCodesToSkip(): array
    {
        if (! property_exists($this, 'responseCodesToSkip')) {
            return ['5\\d\\d'];
        }

        $responseCodesToSkip = (new ReflectionProperty($this, 'responseCodesToSkip'))->getValue($this);

        if (is_array($responseCodesToSkip)) {
            return $responseCodesToSkip;
        }

        return [];
    }

    private function failValidation(AddressValidationFailed $exception, mixed $content): never
    {
        $message = $exception->getVerboseMessage();

        if (is_string($content) && json_validate($content)) {
            $message .= PHP_EOL.json_encode(json_decode($content), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        }

        Assert::fail($message);
    }

    private function getPsr7Factory(): PsrHttpFactory
    {
        if ($this->psr7Factory instanceof PsrHttpFactory) {
            return $this->psr7Factory;
        }

        $psr17Factory = new Psr17Factory;

        return $this->psr7Factory = new PsrHttpFactory(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
        );
    }
}
