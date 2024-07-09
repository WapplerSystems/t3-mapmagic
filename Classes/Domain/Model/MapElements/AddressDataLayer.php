<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\Address\Domain\Repository\AddressRepository;

class AddressDataLayer extends Layer {




    public function initializeMapElement()
    {

        $addressRepository = GeneralUtility::makeInstance(AddressRepository::class);

        $addresses = $addressRepository->findAll();
        //DebugUtility::debug($addresses);

        $addressesOutput = [];

        foreach ($addresses as $address) {
            $addressesOutput[] = [
                'address' => $address->getAddress() . ', ' . $address->getZip() . ' ' . $address->getCity(),
                'lat' => $address->getLatitude(),
                'lng' => $address->getLongitude()
            ];
        }

        $this->properties['addresses'] = $addressesOutput;

        parent::initializeMapElement();
    }




}
