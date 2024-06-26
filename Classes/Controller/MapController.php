<?php

namespace WapplerSystems\Map\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use WapplerSystems\Address\Domain\Repository\AddressRepository;
use WapplerSystems\Map\Domain\Repository\CategoryRepository;

class MapController extends ActionController
{

    public function __construct(readonly AddressRepository $addressRepository, readonly CategoryRepository $categoryRepository)
    {
    }


    public function initializeObject()
    {

        $configuration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        $this->conf['storagePid'] = $configuration['persistence']['storagePid'];
    }


    public function showAction() : ResponseInterface
    {
        $addresses = $this->addressRepository->findAll();


        $this->view->assignMultiple([
            'addresses' => $addresses,
        ]);

        return $this->htmlResponse();
    }



    /**
     * action ajaxSearch
     *
     */
    public function ajaxSearchAction()
    {

        /*********************************************************/
// alternative geocoder
//https://nominatim.openstreetmap.org/search/elzstr.%2010%20rheinhausen?format=json&addressdetails=1&limit=1&polygon_svg=1//
//			$addressData = $this->get_webpage($apiURL);
//        $addresses = $this->addressRepository->findAll();

        /*
        $radius = 1000;
        $storagePid = $this->conf['storagePid'];
        $limit = 300;
        $page = 0;

        $addresses = $this->addressRepository->findLocationsInRadius($latLon, $radius, $categories, $storagePid, $limit, $page, $orderBy = 'distance');

                $categories = $this->categoryRepository->findAll();
        */
        $iconPath = 'fileadmin/ext/map_magic/Resources/Public/Icons/';
        if (!is_dir(Environment::getPublicPath() . '/' . $iconPath)) {
            $fileSystem = new FileSystem();
            if (Environment::getPublicPath() != Environment::getProjectPath()) {
                //  we are in composerMode
                $sourceDir = Environment::getProjectPath() . '/vendor/wsr/mapmagic/Resources/Public/';
            } else {
                $sourceDir = 'typo3conf/ext/map_magic/Resources/Public/';
            }
            $fileSystem->mirror($sourceDir, 'fileadmin/ext/map_magic/Resources/Public/');
            $this->addFlashMessage('Directory ' . $iconPath . ' created for use with own mapIcons!', '', AbstractMessage::INFO);
        }

        /*
                if (!is_dir(Environment::getPublicPath() . '/' . $iconPath)) {
                    $this->addFlashMessage('Directory ' . $iconPath . ' created for use with own mapIcons!', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::INFO);
                    GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/' . $iconPath);
                    $sourceDir = 'typo3conf/ext/map_magic/Resources/Public/MapIcons/';
                    $files = GeneralUtility::getFilesInDir($sourceDir, 'png,gif,jpg');
                    foreach ($files as $file) {
                        copy($sourceDir . $file, $iconPath . $file);
                    }
                }
        */

        // Get the default Settings
        $customStoragePid = $this->conf['storagePid'];
        $querySettings = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Persistence\\Generic\\Typo3QuerySettings');
        $querySettings->setRespectStoragePage(true);
        $querySettings->setStoragePageIds(array($customStoragePid));

        $addresses = $this->addressRepository->findAll();

        if ($this->settings['defaultLanguageUid'] > '') {
            $querySettings->setLanguageUid($this->settings['defaultLanguageUid']);
        }


        $context = GeneralUtility::makeInstance(Context::class);
        $sys_language_uid = $context->getPropertyFromAspect('language', 'id');

        $categories = $this->categoryRepository->findAllOverride($this->conf['storagePid'], $sys_language_uid);

        if ($categories) {
            for ($i = 0; $i < count($categories); $i++) {
                $arr[$i]['uid'] = $categories[$i]['uid'];
                if ($categories[$i]['parent']) {
                    $arr[$i]['parent'] = $categories[$i]['parent'];
                } else $arr[$i]['parent'] = 0;

                $arr[$i]['title'] = $categories[$i]['title'];
            }
        }

        $arr = $arr ?? [];
        if (count($arr) == 0) {
            $this->addFlashMessage('Please insert some sys_categories first!', 'Myleaflet', AbstractMessage::WARNING);
            return $this->responseFactory->createResponse()
                ->withAddedHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody($this->streamFactory->createStream($this->view->render()));
        }
        $categories = $this->buildTree($arr);

        $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
        $sys_language_uid = $languageAspect->getId();
        $this->view->assign('L', $sys_language_uid);

        $this->view->assign('id', $GLOBALS['TSFE']->page['uid']);
        $this->view->assign('categories', $categories);
        $this->view->assign('addresses', $addresses);
        $this->view->assign('locationsCount', count($addresses));

        return $this->responseFactory->createResponse()
            ->withAddedHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->streamFactory->createStream($this->view->render()));

    }


    function get_webpage($url)
    {
        if (ini_get('allow_url_fopen'))
            $this->conf['useCurl'] = 0;
        else
            $this->conf['useCurl'] = 1;

        if ($this->conf['useCurl']) {
            $sessions = curl_init();
            curl_setopt($sessions, CURLOPT_URL, $url);
            curl_setopt($sessions, CURLOPT_HEADER, 0);
            curl_setopt($sessions, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($sessions);
            curl_close($sessions);
        } else {
            $data = \TYPO3\CMS\Core\Utility\GeneralUtility::getURL($url);
        }
        return $data;
    }


}
