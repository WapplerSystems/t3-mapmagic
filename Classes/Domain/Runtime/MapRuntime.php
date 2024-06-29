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

/*
 * Inspired by and partially taken from the Neos.Form package (www.neos.io)
 */

namespace WapplerSystems\MapMagic\Domain\Runtime;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use WapplerSystems\MapMagic\Domain\Exception\RenderingException;
use WapplerSystems\MapMagic\Domain\Model\MapDefinition;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RenderableInterface;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RootRenderableInterface;
use WapplerSystems\MapMagic\Domain\Renderer\RendererInterface;
use WapplerSystems\MapMagic\Domain\Runtime\FormRuntime\Lifecycle\AfterMapStateInitializedInterface;

/**
 * This class implements the *runtime logic* of a form, i.e. deciding which
 * page is shown currently, what the current values of the form are, trigger
 * validation and property mapping.
 *
 * You generally receive an instance of this class by calling {@link \WapplerSystems\MapMagic\Domain\Model\MapDefinition::bind}.
 *
 * Rendering a Form
 * ================
 *
 * That's easy, just call render() on the FormRuntime:
 *
 * /---code php
 * $form = $formDefinition->bind($request);
 * $renderedForm = $form->render();
 * \---
 *
 * Accessing Form Values
 * =====================
 *
 * In order to get the values the user has entered into the form, you can access
 * this object like an array: If a form field with the identifier *firstName*
 * exists, you can do **$form['firstName']** to retrieve its current value.
 *
 * You can also set values in the same way.
 *
 * Rendering Internals
 * ===================
 *
 * The FormRuntime asks the MapDefinition about the configured Renderer
 * which should be used ({@link \WapplerSystems\MapMagic\Domain\Model\MapDefinition::getRendererClassName}),
 * and then trigger render() on this Renderer.
 *
 * This makes it possible to declaratively define how a form should be rendered.
 *
 * Scope: frontend
 * **This class is NOT meant to be sub classed by developers.**
 *
 * @internal High cohesion to MapDefinition, may change any time
 * @todo: Declare final in v12
 */
class MapRuntime implements RootRenderableInterface, \ArrayAccess
{

    protected MapDefinition $mapDefinition;
    protected RequestInterface $request;
    protected ResponseInterface $response;


    /**
     * The current site language configuration.
     *
     * @var SiteLanguage
     */
    protected $currentSiteLanguage;


    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly ConfigurationManagerInterface $configurationManager,
        protected readonly HashService $hashService,
    ) {
        $this->response = new Response();
    }

    public function setRequest(RequestInterface $request)
    {
        $this->request = clone $request;
    }

    public function initialize()
    {
        //$this->initializeCurrentSiteLanguage();
        $this->triggerAfterMapStateInitialized();
    }


    protected function triggerAfterMapStateInitialized(): void
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['afterMapStateInitialized'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if ($hookObj instanceof AfterMapStateInitializedInterface) {
                $hookObj->afterFormStateInitialized($this);
            }
        }
    }


    /**
     * Necessary to know if honeypot information should be stored in the user session info, or in the anonymous session
     *
     * @return bool true when a frontend user is logged, otherwise false
     */
    protected function isFrontendUserAuthenticated(): bool
    {
        return (bool)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('frontend.user', 'isLoggedIn', false);
    }


    /**
     * Determine whether the surrounding content object is cached.
     * If no surrounding content object can be found (which would be strange)
     * we assume a cached request for safety which means that an empty form
     * will be rendered.
     */
    protected function isRenderedCached(): bool
    {
        $contentObject = $this->request->getAttribute('currentContentObject');
        // @todo: this does not work when rendering a cached `FLUIDTEMPLATE` (not nested in `COA_INT`)
        //        Rendering the form other than with the controller, will never work out cleanly.
        //        This likely can only be resolved by deprecating using the form render view helper
        //        other than in a template for the form plugin and covering the use cases the VH was introduced
        //        with a different concept
        return $contentObject === null || $contentObject->getUserObjectType() === ContentObjectRenderer::OBJECTTYPE_USER;
    }


    /**
     * Render this form.
     *
     * @return string|null rendered form
     * @throws RenderingException
     */
    public function render()
    {

        if ($this->mapDefinition->getRendererClassName() === '') {
            throw new RenderingException(sprintf('The form definition "%s" does not have a rendererClassName set.', $this->mapDefinition->getIdentifier()), 1326095912);
        }
        $rendererClassName = $this->mapDefinition->getRendererClassName();
        $renderer = $this->container->get($rendererClassName);
        if (!($renderer instanceof RendererInterface)) {
            throw new RenderingException(sprintf('The renderer "%s" des not implement RendererInterface', $rendererClassName), 1326096024);
        }

        $renderer->setMapRuntime($this);
        return $renderer->render();
    }


    /**
     * @return string The identifier of underlying form
     */
    public function getIdentifier(): string
    {
        return $this->mapDefinition->getIdentifier();
    }

    /**
     * Get the request this object is bound to.
     *
     * This is mostly relevant inside Finishers, where you f.e. want to redirect
     * the user to another page.
     *
     * @return RequestInterface The request this object is bound to
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the response this object is bound to.
     *
     * This is mostly relevant inside Finishers, where you f.e. want to set response
     * headers or output content.
     *
     * @return ResponseInterface the response this object is bound to
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function setMapDefinition(MapDefinition $mapDefinition): void
    {
        $this->mapDefinition = $mapDefinition;
    }


    /**
     * Abstract "type" of this Renderable. Is used during the rendering process
     * to determine the template file or the View PHP class being used to render
     * the particular element.
     */
    public function getType(): string
    {
        return $this->mapDefinition->getType();
    }

    /**
     * @param string $identifier
     * @internal
     */
    public function offsetExists(mixed $identifier): bool
    {
        $identifier = (string)$identifier;

        if (is_callable([$this, 'get' . ucfirst($identifier)])) {
            return true;
        }
        if (is_callable([$this, 'has' . ucfirst($identifier)])) {
            return true;
        }
        if (is_callable([$this, 'is' . ucfirst($identifier)])) {
            return true;
        }
        if (property_exists($this, $identifier)) {
            $propertyReflection = new \ReflectionProperty($this, $identifier);
            return $propertyReflection->isPublic();
        }

        return false;
    }

    /**
     * @param string $identifier
     * @internal
     */
    public function offsetGet(mixed $identifier): mixed
    {
        $identifier = (string)$identifier;
        $getterMethodName = 'get' . ucfirst($identifier);
        if (is_callable([$this, $getterMethodName])) {
            return $this->{$getterMethodName}();
        }
        return null;
    }

    /**
     * @param string $identifier
     * @internal
     */
    public function offsetSet(mixed $identifier, mixed $value): void
    {
        $identifier = (string)$identifier;
    }

    /**
     * @param string $identifier
     * @internal
     */
    public function offsetUnset(mixed $identifier): void
    {
        $identifier = (string)$identifier;
    }

    /**
     * Get all rendering options
     *
     * @return array associative array of rendering options
     */
    public function getRenderingOptions(): array
    {
        return $this->mapDefinition->getRenderingOptions();
    }

    /**
     * Get the renderer class name to be used to display this renderable;
     * must implement RendererInterface
     *
     * @return string the renderer class name
     */
    public function getRendererClassName(): string
    {
        return $this->mapDefinition->getRendererClassName();
    }

    /**
     * Get the label which shall be displayed next to the form element
     */
    public function getLabel(): string
    {
        return $this->mapDefinition->getLabel();
    }

    /**
     * Get the template name of the renderable
     */
    public function getTemplateName(): string
    {
        return $this->mapDefinition->getTemplateName();
    }

    /**
     * Get the underlying form definition from the runtime
     */
    public function getMapDefinition(): MapDefinition
    {
        return $this->mapDefinition;
    }

    /**
     * Get the current site language configuration.
     *
     * @return SiteLanguage
     */
    public function getCurrentSiteLanguage(): ?SiteLanguage
    {
        return $this->currentSiteLanguage;
    }

    /**
     * Override the the current site language configuration.
     *
     * This is typically not needed in production code, but it is very
     * helpful when displaying some kind of "preview" of the form (e.g. form editor).
     *
     * @param SiteLanguage $currentSiteLanguage
     */
    public function setCurrentSiteLanguage(SiteLanguage $currentSiteLanguage): void
    {
        $this->currentSiteLanguage = $currentSiteLanguage;
    }

    /**
     * Initialize the SiteLanguage object.
     * This is mainly used by the condition matcher.
     */
    protected function initializeCurrentSiteLanguage(): void
    {
        if ($this->request->getAttribute('language') instanceof SiteLanguage) {
            $this->currentSiteLanguage = $this->request->getAttribute('language');
        } else {
            $pageId = 0;
            $languageId = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('language', 'id', 0);

            if ($this->getTypoScriptFrontendController() !== null) {
                $pageId = $this->getTypoScriptFrontendController()->id;
            }

            $fakeSiteConfiguration = [
                'languages' => [
                    [
                        'languageId' => $languageId,
                        'title' => 'Dummy',
                        'navigationTitle' => '',
                        'flag' => '',
                        'locale' => '',
                    ],
                ],
            ];

            $this->currentSiteLanguage = GeneralUtility::makeInstance(Site::class, 'map-dummy', $pageId, $fakeSiteConfiguration)
                ->getLanguageById($languageId);
        }
    }


    protected function getFrontendUser(): FrontendUserAuthentication
    {
        return $this->getTypoScriptFrontendController()->fe_user;
    }

    protected function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'] ?? null;
    }

    protected function isRenderableEnabled(RenderableInterface $renderable): bool
    {
        if (!$renderable->isEnabled()) {
            return false;
        }

        while ($renderable = $renderable->getParentRenderable()) {
            if ($renderable instanceof RenderableInterface && !$renderable->isEnabled()) {
                return false;
            }
        }

        return true;
    }
}
