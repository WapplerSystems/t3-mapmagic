<?php

namespace WapplerSystems\Map\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

//use TYPO3\CMS\Core\Http\NullResponse;

use stdClass;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;

use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use WapplerSystems\Map\Domain\Repository\AddressRepository;

use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use WapplerSystems\Map\Domain\Repository\CategoryRepository;

/**
 *
 *
 * @package map_magic
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class AjaxController extends ActionController
{

    /**
     * @var LanguageService
     */
    public $languageService;

    /**
     * AjaxController constructor.
     */
    public function __construct()
    {
    }

    /**
     * AddressRepository
     *
     * @var AddressRepository
     */
    protected $addressRepository;

    /**
     * Inject a addressRepository to enable DI
     *
     * @param AddressRepository $addressRepository
     * @return void
     */
    public function injectAddressRepository(AddressRepository $addressRepository)
    {
        $this->addressRepository = $addressRepository;
    }

    /**
     * categoryRepository
     *
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * Inject a categoryRepository to enable DI
     *
     * @param CategoryRepository $categoryRepository
     * @return void
     */
    public function injectCategoryRepository(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }


    /**
     * action ajaxPage
     * @return string JSON
     */
    public function ajaxPageAction()
    {
        // not used yet
        $requestArguments = $this->request1->getArguments();
        return json_encode($requestArguments);
    }

    /**
     * action ajaxEidGeocode
     * @return stdclass $latLon
     */
    public function ajaxEidGeocodeAction()
    {
        $requestArguments = $this->request1->getParsedBody()['tx_mapmagic_ajax'];

        $address = urlencode($requestArguments['address']);
        $country = urlencode($requestArguments['country']);

        /*
        https://nominatim.openstreetmap.org/search?q=elzstr.%2010%20rheinhausen?format=json&addressdetails=1&limit=1&polygon_svg=1
        max 1 call/sec
        */

        $apiURL = "https://nominatim.openstreetmap.org/search?q=$address,$country&format=json&limit=1";
        $addressData = $this->get_webpage($apiURL);

        $coordinates[1] = json_decode($addressData)[0]->lat;
        $coordinates[0] = json_decode($addressData)[0]->lon;

        $latLon = new stdClass();
        $latLon->lat = (float)$coordinates[1];
        $latLon->lon = (float)$coordinates[0];
        if ($latLon->lat)
            $latLon->status = 'OK';
        else
            $latLon->status = 'NOT FOUND';

        return $latLon;
    }


    function get_webpage($url)
    {
        $sessions = curl_init();
        curl_setopt($sessions, CURLOPT_URL, $url);
        curl_setopt($sessions, CURLOPT_HEADER, 0);
        curl_setopt($sessions, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($sessions, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);
        $data = curl_exec($sessions);
        curl_close($sessions);
        return $data;
    }


    /**
     * @param ServerRequestInterface $request
     * @param TYPO3\CMS\Core\Http\Response $response
     */
    public function indexAction(ServerRequestInterface $request, Response $response)
    {
        switch ($request->getMethod()) {
            case 'GET':
                $response = $this->processGetRequest($request, $response);
                break;
            case 'POST':
                $response = $this->processPostRequest($request, $response);
                break;
            default:
                $response->withStatus(405, 'Method not allowed');
        }

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @param TYPO3\CMS\Core\Http\Response $response
     */
    protected function processGetRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $view = $this->getView();

        $response->withHeader('Content-type', ['text/html; charset=UTF-8']);
        $response->getBody()->write($view->render());
    }

    /**
     * @param ServerRequestInterface $request
     * @param TYPO3\CMS\Core\Http\Response $response
     */
    protected function processPostRequest(ServerRequestInterface $request, $response)
    {
        $queryParams = $request->getQueryParams();

//		$queryParameters = $request->getParsedBody();
//		$pid = (int)$queryParameters['pid'];
//		$queryParams = $queryParameters;

        $frontend = $GLOBALS['TSFE'];

        /** @var TypoScriptService $typoScriptService */
        $typoScriptService = GeneralUtility::makeInstance('TYPO3\CMS\Core\TypoScript\TypoScriptService');
        $this->configuration = $typoScriptService->convertTypoScriptArrayToPlainArray($frontend->tmpl->setup['plugin.']['tx_mapmagic.']);
        $this->settings = $this->configuration['settings'];
        $this->conf['storagePid'] = $this->configuration['persistence']['storagePid'];

        $this->request1 = $request;
        $out = $this->ajaxEidAction();
        return $out;
    }


    /**
     * @return StandaloneView
     */
    protected function getView()
    {
        //    $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $templateService = GeneralUtility::makeInstance(TemplateService::class);
        // get the rootline
        //    $rootLine = $pageRepository->getRootLine($pageRepository->getDomainStartPage(GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY')));
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, 0);

        $rootLine = $rootlineUtility->get();

        // initialize template service and generate typoscript configuration
        $templateService->init();
        $templateService->runThroughTemplates($rootLine);
        $templateService->generateConfig();

//		$fluidView = new StandaloneView();
        $fluidView = GeneralUtility::makeInstance(StandaloneView::class);
        $fluidView->setLayoutRootPaths($templateService->setup['plugin.']['tx_yourext.']['view.']['layoutRootPaths.']);
        $fluidView->setTemplateRootPaths($templateService->setup['plugin.']['tx_yourext.']['view.']['templateRootPaths.']);
        $fluidView->setPartialRootPaths($templateService->setup['plugin.']['tx_yourext.']['view.']['partialRootPaths.']);
        $fluidView->getRequest()->setControllerExtensionName('YourExt');
        $fluidView->setTemplate('index');

        return $fluidView;
    }


    /**
     * action ajaxEid
     * @return string html
     */
    public function ajaxEidAction()
    {
        $requestArguments = $this->request1->getParsedBody()['tx_mapmagic_ajax'];

        // fetching correct language for locallang labels
        $siteConfiguration = $this->request1->getAttribute('site')->getConfiguration();
        for ($i = 0; $i < count($siteConfiguration['languages']); $i++) {
            if ($siteConfiguration['languages'][$i]['typo3Language'] == $requestArguments['language']) {
                $this->language = $siteConfiguration['languages'][$i]['typo3Language'];
            }
        }

        $this->_GP['categories'] = '';
        $requestArguments['categories'] = $requestArguments['categories'] ?? '';
        if ($requestArguments['categories'])
            $this->_GP['categories'] = @implode(',', $requestArguments['categories']);
        // sanitizing categories
        if ($this->_GP['categories'] && preg_match('/^[0-9,]*$/', $this->_GP['categories']) != 1) {
            $this->_GP['categories'] = '';
        }
        if ($this->_GP['categories']) {
            $this->_GP['categories'] = $this->categoryRepository->getCategoryList($this->_GP['categories'], $this->conf['storagePid']);
        }

        if ($this->settings['defaultLanguageUid'] > '') {
            $this->language = $this->settings['defaultLanguageUid'];
        } else {
            $this->language = $requestArguments['language'];
        }

        $latLon = $this->ajaxEidGeocodeAction();

        if ($latLon->status != 'OK') {
            if ($latLon->status == '') $latLon->status = 'There was no status from geocoding returned.';

            $out = '<div class="ajaxMessage">Geocoding Error: ' . $latLon->status . '</div>';
            $out .= '<script	type="text/javascript">
			$(".ajaxMessage").fadeIn(2000);
			</script>';
            return $out;
        } else {
            /*
                         $out .= '<script	type="text/javascript">
                            $("#tx_mapmagic_lat").val(' . $latLon->lat . ');
                            $("#tx_mapmagic_lon").val(' . $latLon->lon . ');
                        </script>';
            */
        }

        $this->_GP['radius'] = (float)$requestArguments['radius'];

        $limit = $this->settings['resultLimit'];

        $page = intval($requestArguments['page']);
        if ($page == -1) {
            $limit = 1000;
            $page = 0;
        }

        if (!$requestArguments['address']) {
            $orderBy = 'city';
        } else {
            $orderBy = 'distance';
        }

        $categoryMode = $this->settings['categorySelectMode'];

        $locations = $this->addressRepository->findLocationsInRadius($latLon, $this->_GP['radius'], $this->_GP['categories'], $this->conf['storagePid'], $this->language, $limit, $page, $orderBy, $categoryMode);
        $allLocations = $this->addressRepository->findLocationsInRadius($latLon, $this->_GP['radius'], $this->_GP['categories'], $this->conf['storagePid'], $this->language, 1000, 0, $orderBy, $categoryMode);


        // field images
        if (is_array($locations)) {
            for ($i = 0; $i < count($locations); $i++) {
                $locations[$i]['infoWindowDescription'] = str_replace(array("\r\n", "\r", "\n"), '<br />', $locations[$i]['description']);
                $locations[$i]['description'] = str_replace(array("\r\n", "\r", "\n"), '<br />', htmlspecialchars($locations[$i]['description'], ENT_QUOTES));
                $address = $locations[$i]['address'];
                $locations[$i]['address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', $locations[$i]['address']);

                $locations[$i]['infoWindowAddress'] = str_replace(array("\r\n", "\r", "\n"), '<br />', htmlspecialchars($address, ENT_QUOTES));

                if ($locations[$i]['image'] > 0) {
                    if ($this->addressRepository->findByUid($locations[$i]['uid'])) {
                        $images = $this->addressRepository->findByUid($locations[$i]['uid'])->getImage();
                    }
                    $locations[$i]['images'] = $images;
                }

            }
        }
        if (!is_array($locations) || count($locations) == 0) {
            $out = '<div class="ajaxMessage">' . LocalizationUtility::translate('noLocationsFound', 'map_magic') . '</div>';
            $out .= '<script	type="text/javascript">';
            // remove marker from map
            $out .= '
				for (var i = 0; i < marker.length; i++) {
					map.removeLayer(marker[i]);
				}
				marker = [];
				map.removeLayer(markerClusterGroup);
				markerClusterGroup = L.markerClusterGroup();
				$(".ajaxMessage").fadeIn(2000);
				</script>';
            return $out;
        }
        $out = '';
        $categories = '';
        $out .= $this->getMarkerJS($locations, $categories, $latLon, $this->_GP['radius']);

        // get  the loctions list

        if ($requestArguments['page'] != -1) { // do not show the list for page loading
            $labels = [
                'distance' => $this->translate('distance'),
                'address' => $this->translate('address'),
                'zip' => $this->translate('zip'),
                'city' => $this->translate('city'),
                'country' => $this->translate('country'),
                'phone' => $this->translate('phone'),
                'email' => $this->translate('email'),
                'fax' => $this->translate('fax'),
                'route' => $this->translate('route'),

            ];
            $out .= $this->getLocationsList($locations, $categories, $allLocations, $labels);
        }

        return $out;
    }


    function getChildren($arr, $id, $children)
    {
        for ($i = 0; $i < count($arr); $i++) {
            if ($arr[$i]['parent'] == $id) {
//				$children .= ',' . $arr[$i]['uid'];
                $children = $this->getChildren($arr, $arr[$i]['uid'], $children);
            }
        }

        return $id . ',' . $children;
//		return $children;
    }


    protected function getMarkerJS($locations, $categories, $latLon, $radius)
    {
        $out = '<script	type="text/javascript">';

        // remove marker from map
        $out .= 'var markerGroup = L.featureGroup(); //.addTo(map);
			for(i=0;i<marker.length;i++) {
				map.removeLayer(marker[i]);
				markerClusterGroup.removeLayer(marker[i]);
			}
			marker = [];
			markerClusterGroup = L.markerClusterGroup();
			';

        for ($i = 0; $i < count($locations); $i++) {
            $lat = $locations[$i]['latitude'];
            $lon = $locations[$i]['longitude'];

            if (!$lat) continue;

            if ($locations[$i]['leafletmapicon']) {
                $out .= '
		
				var mapIcon' . $i . ' = L.icon({
					iconUrl: "/fileadmin/ext/map_magic/Resources/Public/MapIcons/' . $locations[$i]['leafletmapicon'] . '",
					iconSize:     [' . $this->settings["markerIconWidth"] . ' , ' . $this->settings["markerIconHeight"] . ' ], // size of the icon
					iconAnchor:   [' . intval($this->settings["markerIconWidth"] / 2) . ' , ' . $this->settings["markerIconHeight"] . ' ]
				});
				marker[' . $i . '] = L.marker([' . $lat . ',' . $lon . '], {icon: mapIcon' . $i . '}).addTo(markerGroup);
			';

            } else {
                $out .= "marker[$i] = L.marker([$lat, $lon]).addTo(markerGroup);
				";
            }

            // infoWindows
            $out .= $this->renderFluidTemplate('AjaxLocationListInfoWindow.html', array('location' => $locations[$i], 'categories' => $categories, 'i' => $i,
                'startingPoint' => $latLon, 'settings' => $this->settings));

        } // for

        if ($this->settings['enableMarkerClusterer'] == 1) {
            $out .= '
			markerClusterGroup = L.markerClusterGroup();
			markerClusterGroup.clearLayers();
			map.removeLayer(markerClusterGroup);
			markerClusterGroup = L.markerClusterGroup();
			for (var i = 0; i < marker.length; i++) {
				markerClusterGroup.addLayer(marker[i]);
			}
			map.addLayer(markerClusterGroup);
			map.fitBounds(markerClusterGroup.getBounds());
			';
        } else {
            $out .= 'markerGroup = L.featureGroup(marker).addTo(map);
					map.fitBounds(markerGroup.getBounds());';
        }
        return $out . '</script>';
    }


    function getLocationsList($locations, $categories, $allLocations, $labels)
    {
        $out = $this->renderFluidTemplate('AjaxLocationList.html', array('locations' => $locations, 'categories' => $categories, 'labels' => $labels,
            'settings' => $this->settings, 'locationsCount' => count($allLocations)));
        return $out;
    }


    /**
     * Renders the fluid template
     * @param string $template
     * @param array $assign
     * @return string
     */
    public function renderFluidTemplate($template, array $assign = array())
    {
        $configuration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);

        $templateRootPath = $this->configuration['view']['templateRootPaths'][1];

        if (!$templateRootPath)
            $templateRootPath = $this->configuration['view']['templateRootPath'][0];

        $templatePath = GeneralUtility::getFileAbsFileName($templateRootPath . 'Address/' . $template);
        $view = GeneralUtility::makeInstance('TYPO3\\CMS\\Fluid\\View\\StandaloneView');
        $view->setTemplatePathAndFilename($templatePath);
        $view->assignMultiple($assign);
        if ((new Typo3Version())->getMajorVersion() > 11)
            $view->setRequest($this->request1);
        return $view->render();
    }


    /**
     * Returns the translation of $key
     *
     * @param string $key
     * @return string
     */
    protected function translate($key)
    {
        return LocalizationUtility::translate($key, 'map_magic', []);
    }


}
