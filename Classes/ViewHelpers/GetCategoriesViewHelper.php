<?php
namespace WapplerSystems\Map\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;


/**
 *
 *
 * @package TYPO3
 * @subpackage map_magic
 *
 */
class GetCategoriesViewHelper extends AbstractViewHelper
{
    protected $categoryRepository;

    public function initializeArguments()
    {
        $this->registerArgument('parentCategory', 'integer', 'The parent category', true, 0);
        $this->registerArgument('excludeCategories', 'string', 'Exclude categories', false);
        $this->registerArgument('as', 'string', 'Name of the template variable that will contain the categories', true);
    }

    /**
     * Return child categories
     *
     * @return mixed
     * @api
     */
    public function render()
    {
        $parent = $this->categoryRepository->findByUid($this->arguments['parentCategory']);
        $excludeCategories = ($this->arguments['excludeCategories'] ? explode(',', $this->arguments['excludeCategories']) : []);
        $children = $this->categoryRepository->findChildrenByParent($this->arguments['parentCategory'], $excludeCategories);
        $as = (string)$this->arguments['as'];
        $options = []; // for dropdown select

        $options[0] = $parent->getTitle();

        foreach ($children as $child) {
            $options[$child->getUid()] = $child->getTitle();
        }

        $this->templateVariableContainer->add($as, [
            'parent' => $parent,
            'children' => $children,
            'options' => $options
        ]);

        $output = $this->renderChildren();
        $this->templateVariableContainer->remove($as);

        return $output;
    }


}
