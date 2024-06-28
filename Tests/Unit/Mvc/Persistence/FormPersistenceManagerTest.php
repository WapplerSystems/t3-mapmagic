<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\MapMagic\Tests\Unit\Mvc\Persistence;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Mvc\Configuration\TypoScriptService;
use WapplerSystems\MapMagic\Mvc\Persistence\Exception\NoUniqueIdentifierException;
use WapplerSystems\MapMagic\Mvc\Persistence\Exception\PersistenceManagerException;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MapPersistenceManagerTest extends UnitTestCase
{
    #[Test]
    public function loadThrowsExceptionIfPersistenceIdentifierHasNoYamlExtension(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1477679819);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $runtimeCache = $this->getMockBuilder(VariableFrontend::class)
            ->onlyMethods(['get', 'set'])
            ->disableOriginalConstructor()
            ->getMock();

        $runtimeCache
            ->method('get')
            ->willReturn(false);

        $mockMapPersistenceManager->_set('runtimeCache', $runtimeCache);

        $input = '-1:/user_uploads/_example.php';
        $mockMapPersistenceManager->_call('load', $input);
    }

    #[Test]
    public function loadThrowsExceptionIfPersistenceIdentifierIsAExtensionLocationWhichIsNotAllowed(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1484071985);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $runtimeCache = $this->getMockBuilder(VariableFrontend::class)
            ->onlyMethods(['get', 'set'])
            ->disableOriginalConstructor()
            ->getMock();

        $runtimeCache
            ->method('get')
            ->willReturn(false);

        $mockMapPersistenceManager->_set('runtimeCache', $runtimeCache);

        $mockMapPersistenceManager->_set('formSettings', [
            'persistenceManager' => [
                'allowedExtensionPaths' => [],
            ],
        ]);

        $input = 'EXT:map_magic/Resources/Forms/_example.map.yaml';
        $mockMapPersistenceManager->_call('load', $input);
    }

    #[Test]
    public function saveThrowsExceptionIfPersistenceIdentifierHasNoYamlExtension(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1477679820);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $input = '-1:/user_uploads/_example.php';
        $mockMapPersistenceManager->_call('save', $input, []);
    }

    #[Test]
    public function saveThrowsExceptionIfPersistenceIdentifierIsAExtensionLocationAndSaveToExtensionLocationIsNotAllowed(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1477680881);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $mockMapPersistenceManager->_set('formSettings', [
            'persistenceManager' => [
                'allowSaveToExtensionPaths' => false,
            ],
        ]);

        $input = 'EXT:map_magic/Resources/Forms/_example.map.yaml';
        $mockMapPersistenceManager->_call('save', $input, []);
    }

    #[Test]
    public function saveThrowsExceptionIfPersistenceIdentifierIsAExtensionLocationWhichIsNotAllowed(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1484073571);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $runtimeCache = $this->getMockBuilder(VariableFrontend::class)
            ->onlyMethods(['get', 'set'])
            ->disableOriginalConstructor()
            ->getMock();

        $runtimeCache
            ->method('get')
            ->willReturn(false);

        $mockMapPersistenceManager->_set('runtimeCache', $runtimeCache);

        $mockMapPersistenceManager->_set('formSettings', [
            'persistenceManager' => [
                'allowSaveToExtensionPaths' => true,
                'allowedExtensionPaths' => [],
            ],
        ]);

        $input = 'EXT:map_magic/Resources/Forms/_example.map.yaml';
        $mockMapPersistenceManager->_call('save', $input, []);
    }

    #[Test]
    public function deleteThrowsExceptionIfPersistenceIdentifierHasNoYamlExtension(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1472239534);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $input = '-1:/user_uploads/_example.php';
        $mockMapPersistenceManager->_call('delete', $input);
    }

    #[Test]
    public function deleteThrowsExceptionIfPersistenceIdentifierFileDoesNotExists(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1472239535);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['exists'], [], '', false);

        $mockMapPersistenceManager
            ->method('exists')
            ->willReturn(false);

        $input = '-1:/user_uploads/_example.map.yaml';
        $mockMapPersistenceManager->_call('delete', $input);
    }

    #[Test]
    public function deleteThrowsExceptionIfPersistenceIdentifierIsExtensionLocationAndDeleteFromExtensionLocationsIsNotAllowed(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1472239536);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['exists'], [], '', false);

        $mockMapPersistenceManager
            ->method('exists')
            ->willReturn(true);

        $mockMapPersistenceManager->_set('formSettings', [
            'persistenceManager' => [
                'allowDeleteFromExtensionPaths' => false,
            ],
        ]);

        $input = 'EXT:map_magic/Resources/Forms/_example.map.yaml';
        $mockMapPersistenceManager->_call('delete', $input);
    }

    #[Test]
    public function deleteThrowsExceptionIfPersistenceIdentifierIsExtensionLocationWhichIsNotAllowed(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1484073878);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['exists'], [], '', false);

        $runtimeCache = $this->getMockBuilder(VariableFrontend::class)
            ->onlyMethods(['get', 'set'])
            ->disableOriginalConstructor()
            ->getMock();

        $runtimeCache
            ->method('get')
            ->willReturn(false);

        $mockMapPersistenceManager->_set('runtimeCache', $runtimeCache);

        $mockMapPersistenceManager
            ->method('exists')
            ->willReturn(true);

        $mockMapPersistenceManager->_set('formSettings', [
            'persistenceManager' => [
                'allowDeleteFromExtensionPaths' => true,
                'allowedExtensionPaths' => [],
            ],
        ]);

        $input = 'EXT:map_magic/Resources/Forms/_example.map.yaml';
        $mockMapPersistenceManager->_call('delete', $input);
    }

    #[Test]
    public function deleteThrowsExceptionIfPersistenceIdentifierIsStorageLocationAndDeleteFromStorageIsNotAllowed(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1472239516);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['getStorageByUid', 'exists'], [], '', false);

        $mockStorage = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockStorage
            ->method('checkFileActionPermission')
            ->willReturn(false);

        $file = new File(['name' => 'foo', 'identifier' => '', 'mime_type' => ''], $mockStorage);
        $mockStorage
            ->method('getFile')
            ->willReturn($file);

        $mockMapPersistenceManager
            ->method('getStorageByUid')
            ->willReturn($mockStorage);

        $mockMapPersistenceManager
            ->method('exists')
            ->willReturn(true);

        $input = '-1:/user_uploads/_example.map.yaml';
        $mockMapPersistenceManager->_call('delete', $input);
    }

    #[Test]
    public function existsReturnsTrueIfPersistenceIdentifierIsExtensionLocationAndFileExistsAndFileHasYamlExtension(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $runtimeCache = $this->getMockBuilder(VariableFrontend::class)
            ->onlyMethods(['get', 'set'])
            ->disableOriginalConstructor()
            ->getMock();

        $runtimeCache
            ->method('get')
            ->willReturn(false);

        $mockMapPersistenceManager->_set('runtimeCache', $runtimeCache);

        $mockMapPersistenceManager->_set('formSettings', [
            'persistenceManager' => [
                'allowedExtensionPaths' => [
                    'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/',
                ],
            ],
        ]);

        $input = 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.map.yaml';
        self::assertTrue($mockMapPersistenceManager->_call('exists', $input));
    }

    #[Test]
    public function existsReturnsFalseIfPersistenceIdentifierIsExtensionLocationAndFileExistsAndFileHasNoYamlExtension(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);
        $input = 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.txt';
        self::assertFalse($mockMapPersistenceManager->_call('exists', $input));
    }

    #[Test]
    public function existsReturnsFalseIfPersistenceIdentifierIsExtensionLocationAndFileExistsAndExtensionLocationIsNotAllowed(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $mockMapPersistenceManager->_set('formSettings', [
            'persistenceManager' => [
                'allowedExtensionPaths' => [],
            ],
        ]);

        $input = 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.yaml';
        self::assertFalse($mockMapPersistenceManager->_call('exists', $input));
    }

    #[Test]
    public function existsReturnsFalseIfPersistenceIdentifierIsExtensionLocationAndFileNotExistsAndFileHasYamlExtension(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);
        $input = 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/_BlankForm.yaml';
        self::assertFalse($mockMapPersistenceManager->_call('exists', $input));
    }

    #[Test]
    public function existsReturnsTrueIfPersistenceIdentifierIsStorageLocationAndFileExistsAndFileHasYamlExtension(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['getStorageByUid'], [], '', false);

        $mockStorage = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStorage
            ->method('hasFile')
            ->willReturn(true);

        $mockMapPersistenceManager
            ->method('getStorageByUid')
            ->willReturn($mockStorage);

        $input = '-1:/user_uploads/_example.map.yaml';
        self::assertTrue($mockMapPersistenceManager->_call('exists', $input));
    }

    #[Test]
    public function existsReturnsFalseIfPersistenceIdentifierIsStorageLocationAndFileExistsAndFileNoYamlExtension(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['getStorageByUid'], [], '', false);

        $mockStorage = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStorage
            ->method('hasFile')
            ->willReturn(true);

        $mockMapPersistenceManager
            ->method('getStorageByUid')
            ->willReturn($mockStorage);

        $input = '-1:/user_uploads/_example.php';
        self::assertFalse($mockMapPersistenceManager->_call('exists', $input));
    }

    #[Test]
    public function existsReturnsFalseIfPersistenceIdentifierIsStorageLocationAndFileNotExistsAndFileHasYamlExtension(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['getStorageByUid'], [], '', false);

        $mockStorage = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStorage
            ->method('hasFile')
            ->willReturn(false);

        $mockMapPersistenceManager
            ->method('getStorageByUid')
            ->willReturn($mockStorage);

        $input = '-1:/user_uploads/_example.yaml';
        self::assertFalse($mockMapPersistenceManager->_call('exists', $input));
    }

    #[Test]
    public function getUniquePersistenceIdentifierAppendNumberIfPersistenceIdentifierExists(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['exists'], [], '', false);

        $mockMapPersistenceManager
            ->expects(self::exactly(3))
            ->method('exists')
            ->will(self::onConsecutiveCalls(
                self::returnValue(true),
                self::returnValue(true),
                self::returnValue(false)
            ));

        $input = 'example';
        $expected = '-1:/user_uploads/example_2.map.yaml';
        $result = $mockMapPersistenceManager->_call('getUniquePersistenceIdentifier', $input, '-1:/user_uploads/');
        self::assertSame($expected, $result);
    }

    #[Test]
    public function getUniquePersistenceIdentifierAppendTimestampIfPersistenceIdentifierExists(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['exists'], [], '', false);

        $mockMapPersistenceManager
            ->expects(self::exactly(101))
            ->method('exists')
            ->will(self::onConsecutiveCalls(
                ...$this->returnTrue100Times()
            ));

        $input = 'example';
        $expected = '#^-1:/user_uploads/example_([0-9]{10}).map.yaml$#';

        $returnValue = $mockMapPersistenceManager->_call('getUniquePersistenceIdentifier', $input, '-1:/user_uploads/');
        self::assertMatchesRegularExpression($expected, $returnValue);
    }

    /**
     * Helper function to trigger the fallback unique identifier creation after 100 attempts
     */
    private function returnTrue100Times(): array
    {
        $returnValues = [];
        $returnValues = array_pad($returnValues, 100, self::returnValue(true));
        $returnValues[] = self::returnValue(false);
        return $returnValues;
    }

    #[Test]
    public function getUniqueIdentifierThrowsExceptionIfIdentifierExists(): void
    {
        $this->expectException(NoUniqueIdentifierException::class);
        $this->expectExceptionCode(1477688567);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['checkForDuplicateIdentifier'], [], '', false);

        $mockMapPersistenceManager
            ->method('checkForDuplicateIdentifier')
            ->willReturn(true);

        $input = 'example';
        $mockMapPersistenceManager->_call('getUniqueIdentifier', $input);
    }

    #[Test]
    public function getUniqueIdentifierAppendTimestampIfIdentifierExists(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['checkForDuplicateIdentifier'], [], '', false);

        $mockMapPersistenceManager
                ->expects(self::exactly(101))
                ->method('checkForDuplicateIdentifier')
                ->will(self::onConsecutiveCalls(
                    ...$this->returnTrue100Times()
                ));

        $input = 'example';
        $expected = '#^example_([0-9]{10})$#';

        $returnValue = $mockMapPersistenceManager->_call('getUniqueIdentifier', $input);
        self::assertMatchesRegularExpression($expected, $returnValue);
    }

    #[Test]
    public function checkForDuplicateIdentifierReturnsTrueIfIdentifierIsUsed(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['listForms'], [], '', false);

        $mockMapPersistenceManager
            ->expects(self::once())
            ->method('listForms')
            ->willReturn([
                0 => [
                    'identifier' => 'example',
                ],
            ]);

        $input = 'example';
        self::assertTrue($mockMapPersistenceManager->_call('checkForDuplicateIdentifier', $input));
    }

    #[Test]
    public function checkForDuplicateIdentifierReturnsFalseIfIdentifierIsUsed(): void
    {
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, ['listForms'], [], '', false);

        $mockMapPersistenceManager
            ->expects(self::once())
            ->method('listForms')
            ->willReturn([
                0 => [
                    'identifier' => 'example',
                ],
            ]);

        $input = 'other-example';
        self::assertFalse($mockMapPersistenceManager->_call('checkForDuplicateIdentifier', $input));
    }

    public static function metaDataIsExtractedDataProvider(): \Generator
    {
        yield 'enclosed with single quotation marks and escaped single quotation marks within the label' => [
            'maybeRawMapDefinition' => "label: 'Ouverture d''un compte'",
            'expectedMetaData' => ['label' => "Ouverture d'un compte"],
        ];

        yield 'enclosed with double quotation marks and single quotation marks within the label' => [
            'maybeRawFormDefinition' => 'label: "Demo: Just a \'label\'"',
            'expectedMetaData' => ['label' => "Demo: Just a 'label'"],
        ];

        yield 'label enclosed with single quotation marks' => [
            'maybeRawFormDefinition' => "label: 'Demo'",
            'expectedMetaData' => ['label' => 'Demo'],
        ];

        yield 'label enclosed with double quotation marks' => [
            'maybeRawFormDefinition' => 'label: "Demo"',
            'expectedMetaData' => ['label' => 'Demo'],
        ];

        yield 'single quotation mark only at the start of the label' => [
            'maybeRawFormDefinition' => "label: \\'Demo",
            'expectedMetaData' => ['label' => "\\'Demo"],
        ];

        yield 'double quotation mark only at the start of the label' => [
            'maybeRawFormDefinition' => 'label: \"Demo',
            'expectedMetaData' => ['label' => '\"Demo'],
        ];

        yield 'multiple properties are extracted' => [
            'maybeRawFormDefinition' => implode("\n", [
                'type: "type:type"',
                "identifier: 'identifier:identifier'",
                'prototypeName: prototypeName:prototypeName',
                'label: "Demo: Label"',
                'other: "any-other-property"',
            ]),
            'expectedMetaData' => [
                'type' => 'type:type',
                'identifier' => 'identifier:identifier',
                'prototypeName' => 'prototypeName:prototypeName',
                'label' => 'Demo: Label',
            ],
        ];
    }

    #[DataProvider('metaDataIsExtractedDataProvider')]
    #[Test]
    public function metaDataIsExtracted(string $maybeRawFormDefinition, array $expectedMetaData): void
    {
        $formPersistenceManagerMock = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);
        self::assertSame($expectedMetaData, $formPersistenceManagerMock->_call('extractMetaDataFromCouldBeFormDefinition', $maybeRawFormDefinition));
    }

    #[Test]
    public function overrideByTypoScriptSettingsReturnsNotOverriddenConfigurationIfNoTypoScriptOverridesExists(): void
    {
        $mockController = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);
        $mockController->_set('typoScriptSettings', [
            'formDefinitionOverrides' => [
            ],
        ]);

        $input = [
            'identifier' => 'ext-form-identifier',
            'prototypeName' => 'standard',
            'label' => 'Label',
            'renderables' => [
                0 => [
                    'identifier' => 'page-1',
                    'type' => 'Page',
                    'label' => 'Label',
                    'renderables' => [
                        0 => [
                            'identifier' => 'text-1',
                            'type' => 'Text',
                            'label' => 'Label',
                        ],
                    ],
                ],
            ],
        ];

        $expected = [
            'identifier' => 'ext-form-identifier',
            'prototypeName' => 'standard',
            'label' => 'Label',
            'renderables' => [
                0 => [
                    'identifier' => 'page-1',
                    'type' => 'Page',
                    'label' => 'Label',
                    'renderables' => [
                        0 => [
                            'identifier' => 'text-1',
                            'type' => 'Text',
                            'label' => 'Label',
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame($expected, $mockController->_call('overrideByTypoScriptSettings', $input));
    }

    #[Test]
    public function overrideByTypoScriptSettingsReturnsOverriddenConfigurationIfTypoScriptOverridesExists(): void
    {
        $mockController = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $typoScriptServiceMock = $this->createMock(TypoScriptService::class);
        $typoScriptServiceMock->method('resolvePossibleTypoScriptConfiguration')->with(self::anything())
            ->willReturnArgument(0);
        GeneralUtility::addInstance(TypoScriptService::class, $typoScriptServiceMock);

        $mockController->_set('typoScriptSettings', [
            'formDefinitionOverrides' => [
                'ext-form-identifier' => [
                    'label' => 'Label override',
                    'renderables' => [
                        0 => [
                            'renderables' => [
                                0 => [
                                    'label' => 'Label override',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $input = [
            'identifier' => 'ext-form-identifier',
            'prototypeName' => 'standard',
            'label' => 'Label',
            'renderables' => [
                0 => [
                    'identifier' => 'page-1',
                    'type' => 'Page',
                    'label' => 'Label',
                    'renderables' => [
                        0 => [
                            'identifier' => 'text-1',
                            'type' => 'Text',
                            'label' => 'Label',
                        ],
                    ],
                ],
            ],
        ];

        $expected = [
            'identifier' => 'ext-form-identifier',
            'prototypeName' => 'standard',
            'label' => 'Label override',
            'renderables' => [
                0 => [
                    'identifier' => 'page-1',
                    'type' => 'Page',
                    'label' => 'Label',
                    'renderables' => [
                        0 => [
                            'identifier' => 'text-1',
                            'type' => 'Text',
                            'label' => 'Label override',
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame($expected, $mockController->_call('overrideByTypoScriptSettings', $input));
    }

    #[Test]
    public function overrideByTypoScriptSettingsDoesNotEvaluateTypoScriptLookalikeInstructionsFromYamlSettings(): void
    {
        $formDefinitionYaml = [
            'identifier' => 'ext-form-identifier',
            'prototypeName' => 'standard',
            'label' => [
                'value' => 'Label override',
                '_typoScriptNodeValue' => 'TEXT',
            ],
            'renderables' => [
                0 => [
                    'identifier' => 'page-1',
                    'type' => 'Page',
                    'label' => 'Label',
                    'renderables' => [
                        0 => [
                            'identifier' => 'text-1',
                            'type' => 'Text',
                            'label' => 'Label',
                        ],
                    ],
                ],
            ],
        ];

        $formTypoScript = [
            'formDefinitionOverrides' => [
                'ext-form-identifier' => [
                    'renderables' => [
                        0 => [
                            'renderables' => [
                                0 => [
                                    'label' => [
                                        'value' => 'Label override',
                                        '_typoScriptNodeValue' => 'TEXT',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $evaluatedFormTypoScript = [
            'renderables' => [
                0 => [
                    'renderables' => [
                        0 => [
                            'label' => 'Label override',
                        ],
                    ],
                ],
            ],
        ];

        $expected = [
            'identifier' => 'ext-form-identifier',
            'prototypeName' => 'standard',
            'label' => [
                'value' => 'Label override',
                '_typoScriptNodeValue' => 'TEXT',
            ],
            'renderables' => [
                0 => [
                    'identifier' => 'page-1',
                    'type' => 'Page',
                    'label' => 'Label',
                    'renderables' => [
                        0 => [
                            'identifier' => 'text-1',
                            'type' => 'Text',
                            'label' =>  'Label override',
                        ],
                    ],
                ],
            ],
        ];

        $controllerMock = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);

        $typoScriptServiceMock = $this->createMock(TypoScriptService::class);
        $typoScriptServiceMock
            ->method('resolvePossibleTypoScriptConfiguration')
            ->with($formTypoScript['formDefinitionOverrides']['ext-form-identifier'])
            ->willReturn($evaluatedFormTypoScript);
        GeneralUtility::addInstance(TypoScriptService::class, $typoScriptServiceMock);

        $controllerMock->_set('typoScriptSettings', $formTypoScript);

        self::assertSame($expected, $controllerMock->_call('overrideByTypoScriptSettings', $formDefinitionYaml));
    }

    #[Test]
    public function retrieveFileByPersistenceIdentifierThrowsExceptionIfReadFromStorageIsNotAllowed(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1471630578);
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);
        $storage = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $storage
            ->method('checkFileActionPermission')
            ->willReturn(false);
        $file = new File(['name' => 'foo', 'identifier' => '', 'mime_type' => ''], $storage);
        $resourceFactory = $this->getMockBuilder(ResourceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resourceFactory
            ->method('retrieveFileOrFolderObject')
            ->willReturn($file);
        $mockMapPersistenceManager->_set('resourceFactory', $resourceFactory);
        $input = '-1:/user_uploads/example.yaml';
        $mockMapPersistenceManager->_call('retrieveFileByPersistenceIdentifier', $input);
    }

    #[Test]
    public function getOrCreateFileThrowsExceptionIfFolderNotExistsInStorage(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1471630579);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, [
            'getStorageByUid',
        ], [], '', false);

        $mockStorage = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockStorage
            ->method('hasFolder')
            ->willReturn(false);

        $mockMapPersistenceManager
            ->method('getStorageByUid')
            ->willReturn($mockStorage);

        $input = '-1:/user_uploads/example.yaml';
        $mockMapPersistenceManager->_call('getOrCreateFile', $input);
    }

    #[Test]
    public function getOrCreateFileThrowsExceptionIfWriteToStorageIsNotAllowed(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1471630580);

        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, [
            'getStorageByUid',
        ], [], '', false);

        $mockStorage = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockStorage
            ->method('hasFolder')
            ->willReturn(true);

        $mockStorage
            ->method('checkFileActionPermission')
            ->willReturn(false);

        $file = new File(['name' => 'foo', 'identifier' => '', 'mime_type' => ''], $mockStorage);
        $mockStorage
            ->method('getFile')
            ->willReturn($file);

        $mockMapPersistenceManager
            ->method('getStorageByUid')
            ->willReturn($mockStorage);

        $input = '-1:/user_uploads/example.yaml';
        $mockMapPersistenceManager->_call('getOrCreateFile', $input);
    }

    #[Test]
    public function getStorageByUidThrowsExceptionIfStorageNotExists(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1471630581);
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);
        $mockStorageRepository = $this->getMockBuilder(StorageRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStorageRepository
            ->method('findByUid')
            ->willReturn(null);
        $mockMapPersistenceManager->_set('storageRepository', $mockStorageRepository);
        $mockMapPersistenceManager->_call('getStorageByUid', -1);
    }

    #[Test]
    public function getStorageByUidThrowsExceptionIfStorageIsNotBrowsable(): void
    {
        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionCode(1471630581);
        $mockMapPersistenceManager = $this->getAccessibleMock(MapPersistenceManager::class, null, [], '', false);
        $mockStorageRepository = $this->getMockBuilder(StorageRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStorage = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStorage
            ->method('isBrowsable')
            ->willReturn(false);
        $mockStorageRepository
            ->method('findByUid')
            ->willReturn($mockStorage);
        $mockMapPersistenceManager->_set('storageRepository', $mockStorageRepository);
        $mockMapPersistenceManager->_call('getStorageByUid', -1);
    }

    public static function isAllowedPersistencePathReturnsPropperValuesDataProvider(): array
    {
        return [
            [
                'persistencePath' => '',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => '-1:/user_uploads',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => '-1:/user_uploads/',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => '-1:/user_uploads/example.map.yaml',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.map.yaml',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => [],
                'expected' => false,
            ],

            [
                'persistencePath' => '-1:/user_uploads/',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/some_path/'],
                'expected' => false,
            ],
            [
                'persistencePath' => '-1:/user_uploads/',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/user_uploads/'],
                'expected' => true,
            ],
            [
                'persistencePath' => '-1:/user_uploads',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/user_uploads/'],
                'expected' => true,
            ],
            [
                'persistencePath' => '-1:/user_uploads/',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/user_uploads'],
                'expected' => true,
            ],

            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/',
                'allowedExtensionPaths' => ['EXT:some_extension/Tests/Unit/Mvc/Persistence/Fixtures/'],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/',
                'allowedExtensionPaths' => ['EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/'],
                'allowedFileMounts' => [],
                'expected' => true,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures',
                'allowedExtensionPaths' => ['EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/'],
                'allowedFileMounts' => [],
                'expected' => true,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/',
                'allowedExtensionPaths' => ['EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures'],
                'allowedFileMounts' => [],
                'expected' => true,
            ],

            [
                'persistencePath' => '-1:/user_uploads/example.yaml',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/some_path/'],
                'expected' => false,
            ],
            [
                'persistencePath' => '-1:/user_uploads/example.map.yaml',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/some_path/'],
                'expected' => false,
            ],
            [
                'persistencePath' => '-1:/user_uploads/example.yaml',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/user_uploads/'],
                'expected' => false,
            ],
            [
                'persistencePath' => '-1:/user_uploads/example.yaml',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/user_uploads'],
                'expected' => false,
            ],
            [
                'persistencePath' => '-1:/user_uploads/example.map.yaml',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/user_uploads/'],
                'expected' => true,
            ],
            [
                'persistencePath' => '-1:/user_uploads/example.map.yaml',
                'allowedExtensionPaths' => [],
                'allowedFileMounts' => ['-1:/user_uploads'],
                'expected' => true,
            ],

            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.txt',
                'allowedExtensionPaths' => ['EXT:some_extension/Tests/Unit/Mvc/Persistence/Fixtures/'],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.map.yaml',
                'allowedExtensionPaths' => ['EXT:some_extension/Tests/Unit/Mvc/Persistence/Fixtures/'],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.txt',
                'allowedExtensionPaths' => ['EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/'],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.txt',
                'allowedExtensionPaths' => ['EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures'],
                'allowedFileMounts' => [],
                'expected' => false,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.map.yaml',
                'allowedExtensionPaths' => ['EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/'],
                'allowedFileMounts' => [],
                'expected' => true,
            ],
            [
                'persistencePath' => 'EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures/BlankForm.map.yaml',
                'allowedExtensionPaths' => ['EXT:map_magic/Tests/Unit/Mvc/Persistence/Fixtures'],
                'allowedFileMounts' => [],
                'expected' => true,
            ],
        ];
    }

    #[DataProvider('isAllowedPersistencePathReturnsPropperValuesDataProvider')]
    #[Test]
    public function isAllowedPersistencePathReturnsPropperValues(string $persistencePath, array $allowedExtensionPaths, array $allowedFileMounts, $expected): void
    {
        $formPersistenceManagerMock = $this->getAccessibleMock(MapPersistenceManager::class, ['getStorageByUid'], [], '', false);

        $runtimeCacheMock = $this->getMockBuilder(VariableFrontend::class)
            ->onlyMethods(['get', 'set'])
            ->disableOriginalConstructor()
            ->getMock();

        $runtimeCacheMock
            ->method('get')
            ->willReturn(false);

        $storageMock = $this->getMockBuilder(ResourceStorage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $storageMock
            ->method('getRootLevelFolder')
            ->willReturn(new Folder($storageMock, '', ''));

        $storageMock
            ->method('getFileMounts')
            ->willReturn([]);

        $storageMock
            ->method('getFolder')
            ->willReturn(new Folder($storageMock, '', ''));

        $formPersistenceManagerMock
            ->method('getStorageByUid')
            ->willReturn($storageMock);

        $formPersistenceManagerMock->_set('runtimeCache', $runtimeCacheMock);
        $formPersistenceManagerMock->_set('formSettings', [
            'persistenceManager' => [
                'allowedExtensionPaths' => $allowedExtensionPaths,
                'allowedFileMounts' => $allowedFileMounts,
            ],
        ]);

        self::assertEquals($expected, $formPersistenceManagerMock->_call('isAllowedPersistencePath', $persistencePath));
    }
}
