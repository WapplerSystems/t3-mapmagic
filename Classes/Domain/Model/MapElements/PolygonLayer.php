<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

use TYPO3\CMS\Core\Utility\DebugUtility;

class PolygonLayer extends Layer {



    public function getJavaScript(): string
    {
        return 'const '.$this->getUniqueIdentifier().' = L.polygon('.($this->properties['latlngs'] ?? '[]').').addTo('.$this->getRootMap()->getIdentifier().');';
    }

}
