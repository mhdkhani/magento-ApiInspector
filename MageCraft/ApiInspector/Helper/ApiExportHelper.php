<?php

namespace MageCraft\ApiInspector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Webapi\Model\Rest\Config as RestConfig;
use Magento\Webapi\Model\Config as WebapiConfig;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class ApiExportHelper extends AbstractHelper
{
    /**
     * @var Json
     */
    private $json;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var RestConfig
     */
    private $restConfig;
    /**
     * @var WebapiConfig
     */
    private $webApiConfig;

    public function __construct(
        Context      $context,
        RestConfig   $restConfig,
        WebapiConfig $webApConfig,
        Json         $json,
        Filesystem   $filesystem
    )
    {
        $this->restConfig = $restConfig;
        $this->webApiConfig = $webApConfig;
        $this->json = $json;
        $this->filesystem = $filesystem;
        parent::__construct($context);
    }

    /**
     * Generates a Postman-compatible collection of all Magento 2 REST API routes
     * and writes it to a JSON file in the var directory.
     *
     * - Extracts all service routes and groups them by API version and service group.
     * - Builds request definitions including headers, auth, and sample body.
     * - Serializes and saves the collection as a JSON file.
     *
     * @return string Absolute path to the generated Postman JSON file.
     */
    public function getRestApiCollection()
    {
        $services = $this->webApiConfig->getServices();

        $collection = [
            'info' => [
                'name' => 'Magento 2 REST APIs',
                '_postman_id' => uniqid(),
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
            ],
            'item' => []
        ];

        $folders = [];

        foreach ($services['routes'] as $route => $serviceData) {
            foreach ($serviceData as $httpMethod => $routeInfo) {
                $serviceClass = (isset($routeInfo['service']['class']) && $routeInfo['service']['class'] != '') ? $routeInfo['service']['class'] : '';
                $serviceMethod = (isset($routeInfo['service']['method']) && $routeInfo['service']['method'] != '') ? $routeInfo['service']['method'] : '';
                $method = strtoupper($httpMethod);
                $resources = [];
                foreach ($routeInfo['resources'] as $key => $value)
                    $resources[] = $key;

                $acl = (count($resources)) ? implode(',', $resources) : 'anonymous';
                preg_match('#^/([^/]+)(?:/([^/]+))?#', $route, $matches);
                $version = isset($matches[1]) ? strtoupper($matches[1]) : 'General';
                $group = isset($matches[2]) ? $matches[2] : 'Misc';
                $requestData = [
                    'name' => $method . ' ' . $route,
                    'request' => [
                        'method' => $method,
                        'header' => [],
                        'url' => [
                            'raw' => '{{baseUrl}}' . '/rest' . $route,
                            'host' => ['{{baseUrl}}'],
                            'path' => array_merge(['rest'], explode('/', ltrim($route, '/')))
                        ],
                        'body' => [
                            'mode' => 'raw',
                            // 'raw' => '{}'
                            'raw' => $this->getMethodInputExample($serviceClass, $serviceMethod)
                        ],
                        'description' => "ACL: " . $acl
                    ]
                ];
                if ($acl !== 'anonymous') {
                    $requestData['request']['auth'] = [
                        'type' => 'bearer',
                        'bearer' => [
                            ['key' => 'token', 'value' => '{{token}}', 'type' => 'string']
                        ]
                    ];
                }
                $folders[$version][$group][] = $requestData;
            }
        }
        foreach ($folders as $version => $groups) {
            $groupItems = [];
            foreach ($groups as $group => $items) {
                $groupItems[] = [
                    'name' => $group,
                    'item' => $items
                ];
            }

            $collection['item'][] = [
                'name' => $version,
                'item' => $groupItems
            ];
        }

        $outputPath = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)
            ->getAbsolutePath('rest-api-postman.json');

        file_put_contents($outputPath, $this->json->serialize($collection));

        return $outputPath;
    }

    /**
     * Generates a sample request body for a given service method by inspecting
     * the method’s parameter types using PHP Reflection.
     *
     * If the method has a complex object parameter, it builds a sample structure
     * recursively based on the class's getter methods.
     *
     * @param string $serviceClass Fully qualified class name of the service
     * @param string $serviceMethod Method name in the service class
     *
     * @return string JSON-formatted string representing the sample input structure
     */


    protected function getMethodInputExample($serviceClass, $serviceMethod)
    {
        try {
            $refMethod = new \ReflectionMethod($serviceClass, $serviceMethod);
            $params = $refMethod->getParameters();
            $body = [];

            foreach ($params as $param) {
                $type = $param->getType();
                if ($type && !$type->isBuiltin()) {
                    $className = $type->getName();
                    $body = $this->generateClassStructure($className);
                    break;
                }
            }

            return json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            return '{}';
        }
    }


    /**
     * Generates a sample request body for a given service method by inspecting
     * the method’s parameter types using PHP Reflection.
     *
     * If the method has a complex object parameter, it builds a sample structure
     * recursively based on the class's getter methods.
     *
     * @param string $serviceClass Fully qualified class name of the service
     * @param string $serviceMethod Method name in the service class
     *
     * @return string JSON-formatted string representing the sample input structure
     */

    protected function generateClassStructure($className)
    {
        if (!class_exists($className) && !interface_exists($className)) {
            return [];
        }

        $structure = [];
        $refClass = new \ReflectionClass($className);

        foreach ($refClass->getMethods() as $method) {
            if (strpos($method->getName(), 'get') === 0 && $method->getNumberOfRequiredParameters() === 0) {
                $prop = lcfirst(substr($method->getName(), 3));
                $returnType = $method->getReturnType();

                if ($returnType) {
                    if ($returnType->isBuiltin()) {
                        $structure[$prop] = $returnType->getName(); // string, int, etc.
                    } else {
                        $structure[$prop] = $this->generateClassStructure($returnType->getName());
                    }
                } else {
                    $structure[$prop] = 'mixed';
                }
            }
        }
        return $structure;
    }


}
