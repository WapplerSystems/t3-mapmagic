<?php

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Hooks;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Slot\FilePersistenceSlot;

/**
 * @internal
 */
class ImportExportHook
{
    public function beforeAddSysFileRecordOnImport(array $params): void
    {
        $fileRecord = $params['fileRecord'];
        $temporaryFile = $params['temporaryFile'];

        $formPersistenceSlot = GeneralUtility::makeInstance(FilePersistenceSlot::class);
        $formPersistenceSlot->allowInvocation(
            FilePersistenceSlot::COMMAND_FILE_ADD,
            implode(':', [$fileRecord['storage'], $fileRecord['identifier']]),
            $formPersistenceSlot->getContentSignature(file_get_contents($temporaryFile))
        );
    }
}
