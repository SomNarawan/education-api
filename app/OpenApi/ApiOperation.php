<?php

namespace App\OpenApi;

use Attribute;
use OpenApi\Spec as OA;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApiOperation extends OA\Operation
{
    /**
     * @param  list<string>  $tags
     * @param  list<OA\Parameter>|null  $parameters
     * @param  list<OA\Response>|null  $responses
     * @param  list<int>  $errors
     * @param  list<OA\Security\Requirement>|null  $security
     * @param  list<string|OA\HttpMethod>|null  $methods
     */
    public function __construct(
        string|OA\HttpMethod|null $method,
        string $path,
        string $operationId,
        string $summary,
        array $tags,
        ?array $parameters = null,
        ?OA\RequestBody $requestBody = null,
        ?string $requestSchema = null,
        string $responseSchema = 'ApiSuccess',
        string $errorSchema = 'ApiError',
        int $success = 200,
        array $errors = [401],
        ?array $responses = null,
        ?array $security = null,
        ?array $methods = null,
        ?string $description = null,
    ) {
        $parameters = [...$this->pathParameters($path), ...($parameters ?? [])];
        $requestBody ??= $requestSchema === null ? null : new OA\RequestBody(
            required: true,
            content: new OA\MediaType\Json(ref: '#/components/schemas/'.$requestSchema),
        );
        $responses ??= [
            new OA\Response(
                response: $success,
                description: $success === 201 ? 'Created successfully' : 'Successful response',
                content: new OA\MediaType\Json(ref: '#/components/schemas/'.$responseSchema),
            ),
            ...array_map(
                fn (int $status): OA\Response => new OA\Response(
                    response: $status,
                    description: self::errorDescription($status),
                    content: new OA\MediaType\Json(ref: '#/components/schemas/'.($status === 422 ? 'ValidationError' : $errorSchema)),
                ),
                $errors,
            ),
        ];

        parent::__construct(
            path: $path,
            method: $method,
            operationId: $operationId,
            summary: $summary,
            description: $description,
            tags: $tags,
            parameters: $parameters,
            requestBody: $requestBody,
            responses: $responses,
            security: $security,
            methods: $methods,
        );
    }

    /** @return list<OA\Parameter\Path> */
    private function pathParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return array_map(
            fn (string $name): OA\Parameter\Path => new OA\Parameter\Path(
                name: $name,
                description: self::pathDescription($name),
                schema: new OA\Schema(
                    type: $name === 'id' ? OA\Type::Integer : OA\Type::String,
                    minimum: $name === 'id' ? 1 : null,
                    example: match ($name) {
                        'id' => 1,
                        'studentCode' => '6612345678',
                        'nontriId' => 'example.user',
                        'type' => 'provinces',
                        default => 'example',
                    },
                ),
            ),
            $matches[1],
        );
    }

    private static function errorDescription(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Missing or invalid credentials',
            403 => 'Forbidden',
            404 => 'Resource not found',
            409 => 'Conflict',
            422 => 'Validation failed',
            429 => 'Too many requests',
            500 => 'Internal server error',
            503 => 'Dependent service unavailable',
            default => 'Error response',
        };
    }

    private static function pathDescription(string $name): string
    {
        return match ($name) {
            'id' => 'Numeric resource identifier',
            'studentCode' => 'Student code',
            'nontriId' => 'Kasetsart University Nontri account identifier',
            'type' => 'List-of-value type',
            default => $name,
        };
    }
}
