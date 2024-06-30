<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

class MarkerLayer extends Layer {



    public function getJavaScript(): string
    {
        return 'const '.$this->getUniqueIdentifier().' = L.marker('.($this->properties['latlng'] ?? '[]').').addTo('.$this->getRootMap()->getIdentifier().');';
    }

}
