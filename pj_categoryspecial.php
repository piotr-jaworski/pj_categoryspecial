<?php

if (!defined('_PS_VERSION_')) {
    exit;
}
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShopBundle\Form\Admin\Type\YesAndNoChoiceType;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use PjCategorySpecial\Entity\CategorySpecial;

class Pj_CategorySpecial extends Module
{
    const _REDIRECT_CMS_ = 'PJ_CATEGORYSPECIAL_REDIRECT_CMS';

    public function __construct()
    {
        $this->name = 'pj_categoryspecial';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Piotr Jaworski';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('PJ Category Special', [], 'Modules.Pjcategoryspecial.Admin');
        $this->description = $this->trans('Aditional field for category', [], 'Modules.Pjcategoryspecial.Admin');

        $this->confirmUninstall = $this->trans('All settings will be cleared, are You sure?', [], 'Modules.Pjcategoryspecial.Admin');

        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => _PS_VERSION_);
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('actionAfterCreateCategoryFormHandler') &&
            $this->registerHook('actionAfterUpdateCategoryFormHandler') &&
            $this->registerHook('actionCategoryFormBuilderModifier') &&
            $this->registerHook('actionCategoryGridDefinitionModifier') &&
            $this->registerHook('actionCategoryGridQueryBuilderModifier') &&
            $this->registerHook('filterCategoryContent');
    }

    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall() && Configuration::deleteByName(self::_REDIRECT_CMS_);
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $cms_id = strval(Tools::getValue(self::_REDIRECT_CMS_));

            if (
                !$cms_id ||
                empty($cms_id) ||
                !Validate::isUnsignedId($cms_id)
            ) {
                $output .= $this->displayError($this->trans('Invalid Configuration value', [], 'Modules.Pjcategoryspecial.Admin'));
            } else {
                Configuration::updateValue(self::_REDIRECT_CMS_, $cms_id);
                $output .= $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Pjcategoryspecial.Admin'));
            }
        }

        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');
        $CMS = CMS::getCMSPages($this->context->language->id, null, true, $this->context->shop->id);
        // Init Fields form array
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->trans('Settings', [], 'Modules.Pjcategoryspecial.Admin'),
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->trans('Redirect CMS', [], 'Modules.Pjcategoryspecial.Admin'),
                    'name' => self::_REDIRECT_CMS_,
                    'required' => true,
                    'options' => array(
                        'query' => array_merge([['id_cms' => '', 'meta_title' => '---']], $CMS),
                        'id' => 'id_cms',
                        'name' => 'meta_title',
                    ),
                ]
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Modules.Pjcategoryspecial.Admin'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->trans('Save', [], 'Modules.Pjcategoryspecial.Admin'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->trans('Back to list', [], 'Modules.Pjcategoryspecial.Admin')
            ]
        ];

        // Load current value
        $helper->fields_value[self::_REDIRECT_CMS_] = Tools::getValue(self::_REDIRECT_CMS_, Configuration::get(self::_REDIRECT_CMS_));

        return $helper->generateForm($fieldsForm);
    }

    // hook pozwalający na dodanie definicji dodatkowej kolumny na widoku listy kategorii
    public function hookActionCategoryGridDefinitionModifier(array $params)
    {
        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];

        $translator = $this->getTranslator();

        $definition
            ->getColumns()
            ->addAfter(
                'active',
                (new ToggleColumn('special'))
                    ->setName($translator->trans('Special', [], 'Modules.Pjcategoryspecial.Admin'))
                    ->setOptions([
                        'field' => 'special',
                        'primary_field' => 'id_category',
                        // ścieżka do kontrolera znajdującego się w ./src/Controller/Admin/CategorySpecialController
                        // sama ścieżka zdefiniowana jest w pliku konfiguracyjnym ./config/routes.yml
                        'route' => 'pj_categoryspecial_toggle_is_special',
                        'route_param_name' => 'categoryId',
                    ])
            )
        ;

        $definition->getFilters()->add(
            (new Filter('special', YesAndNoChoiceType::class))
            ->setAssociatedColumn('special')
        );
    }

    // hook w którym robię dodatkowy join do customowej tabeli zawierającej dodatkowe pole
    public function hookActionCategoryGridQueryBuilderModifier(array $params)
    {
        /** @var QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];

        /** @var CustomerFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'];

        $searchQueryBuilder->addSelect(
            'IF(pjcs.`special` IS NULL,0,pjcs.`special`) AS `special`'
        );

        $searchQueryBuilder->leftJoin(
            'c',
            '`' . pSQL(_DB_PREFIX_) . 'category_special`',
            'pjcs',
            'pjcs.`id_category` = c.`id_category`'
        );

        if ('special' === $searchCriteria->getOrderBy()) {
            $searchQueryBuilder->orderBy('pjcs.`special`', $searchCriteria->getOrderWay());
        }

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ('special' === $filterName) {
                $searchQueryBuilder->andWhere('pjcs.`special` = :special');
                $searchQueryBuilder->setParameter('special', $filterValue);

                if (!$filterValue) {
                    $searchQueryBuilder->orWhere('pjcs.`special` IS NULL');
                }
            }
        }
    }

    // hook modyfikujący formularz dodawania/edycji kategorii
    public function hookActionCategoryFormBuilderModifier(array $params)
    {
        $id_category = (int)$params['id'];

        /** @var FormBuilderInterface $formBuilder */
        $params['form_builder']->add('pj_category_special', SwitchType::class, [
            'label' => $this->trans('Is special category?', [], 'Modules.Pjcategoryspecial.Admin'),
            'required' => false,
            'help' => $this->trans(
                'Special category needs customer aditional account in X13 domain, otherwise will be redirected',
                [],
                'Modules.Pjcategoryspecial.Admin'
            ),
        ]);

        $params['form_builder']->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
        });

        $cs = CategorySpecial::getByCategory($id_category);
        $params['data']['pj_category_special'] = $cs->special;

        $params['form_builder']->setData($params['data']);
    }

    // wywołanie funkcji zapisującej dodatkowe pole formularza podczas edycji kategorii
    public function hookActionAfterUpdateCategoryFormHandler(array $params)
    {
        $this->updateCategorySpecial($params);
    }

    // wywołanie funkcji zapisującej dodatkowe pole formularza podczas dodawania kategorii
    public function hookActionAfterCreateCategoryFormHandler(array $params)
    {
        $this->updateCategorySpecial($params);
    }

    // hook wywoływany tylko w kontrolerze kategorii robię w nim dwie rzeczy
    // 1. sprawdzam czy kategoria jest specjalna i przekierowuję użytkownika jeśli tak i nie jest on klientem mającym e-mail w domenie x13.pl
    // 2. przypisuję dodatkowe pole 'special' żeby było dostępne do użytku w themie
    public function hookFilterCategoryContent(array $params)
    {
        $params['object'];
        $cs = CategorySpecial::getByCategory($params['object']['id']);
        if($cs->special && (!$this->context->customer->isLogged() || !preg_match('/[^@]+@x13.pl/i', $this->context->customer->email))){
            $cms_id = Configuration::get(self::_REDIRECT_CMS_);
            header('HTTP/1.1 301 Moved Permanently');
            if($cms_id){
                Tools::redirect($this->context->link->getCmsLink($cms_id));
            }else{
                // jesli nie skonfigurowaliśmy strony cms, przekierowuję na stronę główną
                Tools::redirect($this->context->link->getPageLink('index'));
            }
        }

        $params['object']['special'] = (bool)$cs->special;
        return $params;
    }

    protected function updateCategorySpecial($params)
    {
        $id_category = $params['id'];
        /** @var array $categoryFormData */
        $categoryFormData = $params['form_data'];

        $is_special = $categoryFormData['pj_category_special'];
        $cs = CategorySpecial::getByCategory($id_category);
        $cs->special = $is_special;
        $cs->save();
    }

}
