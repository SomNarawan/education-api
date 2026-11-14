<?php

namespace Tests\Feature\OpenApi;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiGenerationTest extends TestCase
{
    public function test_it_generates_an_openapi_31_document_without_warnings(): void
    {
        $document = $this->generateDocument();

        $this->assertSame('3.1.0', $document['openapi']);
        $this->assertSame('Education API', $document['info']['title']);
        $this->assertNotEmpty($document['info']['version']);
    }

    public function test_it_serves_swagger_ui(): void
    {
        $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('Swagger UI', escape: false);
    }

    public function test_it_declares_expected_security_schemes(): void
    {
        $document = $this->generateDocument();
        $schemes = $document['components']['securitySchemes'];

        $this->assertSame([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ], $schemes['bearerAuth']);
        $this->assertSame([
            'type' => 'apiKey',
            'name' => 'X-API-KEY',
            'in' => 'header',
        ], $schemes['portalApiKey']);
    }

    public function test_it_documents_the_health_endpoint_as_public(): void
    {
        $document = $this->generateDocument();
        $operation = $document['paths']['/api/health']['get'];

        $this->assertSame([], $operation['security']);
        $this->assertArrayHasKey('200', $operation['responses']);
        $this->assertArrayHasKey('503', $operation['responses']);
    }

    public function test_it_covers_every_application_api_route_and_method(): void
    {
        $document = $this->generateDocument();
        $expected = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (! str_starts_with($route->uri(), 'api/') || ! str_starts_with($action, 'App\\')) {
                continue;
            }

            $path = '/'.$route->uri();

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $expected[] = strtolower($method).' '.$path;
            }
        }

        $documented = [];

        foreach ($document['paths'] as $path => $pathItem) {
            foreach (array_intersect(array_keys($pathItem), ['get', 'post', 'put', 'patch', 'delete']) as $method) {
                $documented[] = $method.' '.$path;
            }
        }

        sort($expected);
        sort($documented);

        $this->assertSame([], array_values(array_diff($expected, $documented)), 'Laravel API operations missing from OpenAPI');
        $this->assertSame([], array_values(array_diff($documented, $expected)), 'OpenAPI operations without a Laravel API route');
    }

    public function test_it_applies_endpoint_specific_security(): void
    {
        $document = $this->generateDocument();

        $this->assertSame([], $document['paths']['/api/auth/portal-login']['post']['security']);
        $this->assertSame([], $document['paths']['/api/mock-login/search']['get']['security']);
        $this->assertSame([['portalApiKey' => []]], $document['paths']['/api/portal-main-student/check-user/{nontriId}']['get']['security']);
        $this->assertSame([['bearerAuth' => []]], $document['security']);
    }

    public function test_it_defines_exact_authentication_and_portal_main_response_contracts(): void
    {
        $document = $this->generateDocument();

        $this->assertSame(
            '#/components/schemas/CurrentUserResponse',
            $document['paths']['/api/me']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/PortalMainCheckUser',
            $document['paths']['/api/portal-main-student/check-user/{nontriId}']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/PortalMainStudent',
            $document['paths']['/api/portal-main-student/get-user-data-by-nontri/{nontriId}']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/PortalMainStudentList',
            $document['paths']['/api/portal-main-student/get-user-data-list-by-nontri']['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/NontriSearchResult',
            $document['paths']['/api/portal-main-student/search-nontri']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/PortalMainError',
            $document['paths']['/api/portal-main-student/check-user/{nontriId}']['get']['responses']['401']['content']['application/json']['schema']['$ref'],
        );
    }

    public function test_it_documents_uploads_and_downloads_with_file_media_types(): void
    {
        $document = $this->generateDocument();

        $studentImport = $document['paths']['/api/students/import']['post'];
        $studentTemplate = $document['paths']['/api/students/import/template']['get'];

        $this->assertArrayHasKey('multipart/form-data', $studentImport['requestBody']['content']);
        $this->assertSame('binary', $studentImport['requestBody']['content']['multipart/form-data']['schema']['properties']['file']['format']);
        $this->assertArrayHasKey('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $studentTemplate['responses']['200']['content']);
    }

    public function test_operations_have_required_contract_metadata_and_local_references_resolve(): void
    {
        $document = $this->generateDocument();
        $operationIds = [];

        foreach ($document['paths'] as $path => $pathItem) {
            foreach (array_intersect(array_keys($pathItem), ['get', 'post', 'put', 'patch', 'delete']) as $method) {
                $operation = $pathItem[$method];
                $label = strtoupper($method).' '.$path;

                $this->assertNotEmpty($operation['operationId'] ?? null, $label.' has no operationId');
                $this->assertNotEmpty($operation['summary'] ?? null, $label.' has no summary');
                $this->assertNotEmpty($operation['tags'] ?? null, $label.' has no tags');
                $this->assertNotEmpty($operation['responses'] ?? null, $label.' has no responses');
                $operationIds[] = $operation['operationId'];
            }
        }

        $this->assertSame($operationIds, array_values(array_unique($operationIds)), 'operationId values must be unique');
        $this->assertLocalReferencesResolve($document, $document);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateDocument(): array
    {
        $exitCode = Artisan::call('l5-swagger:generate');

        $this->assertSame(0, $exitCode, Artisan::output());

        $path = storage_path('api-docs/api-docs.json');
        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $document
     */
    private function assertLocalReferencesResolve(array $node, array $document): void
    {
        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, '#/')) {
                $target = $document;

                foreach (explode('/', substr($value, 2)) as $segment) {
                    $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
                    $this->assertIsArray($target, 'Invalid local reference: '.$value);
                    $this->assertArrayHasKey($segment, $target, 'Unresolved local reference: '.$value);
                    $target = $target[$segment];
                }
            }

            if (is_array($value)) {
                $this->assertLocalReferencesResolve($value, $document);
            }
        }
    }
}
