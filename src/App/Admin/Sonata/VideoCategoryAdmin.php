<?php

namespace App\Admin\Sonata;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Class VideoCategoryAdmin
 */
class VideoCategoryAdmin extends AbstractAdmin
{
    const MENU_PRIORITY_LABEL = 'Menu position';

    /**
     * @return array
     */
    public function getBatchActions()
    {
        return [];
    }

    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('title')
            ->add('alias');
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        unset($this->listModes['mosaic']);

        $listMapper
            ->add('uuid')
            ->add('title')
            ->add('alias')
            ->add('menuPriority', IntegerType::class, ['label' => self::MENU_PRIORITY_LABEL])
            ->add('_action', null, array(
                'actions' => array(
                    'show'   => array(),
                    'edit'   => array(),
                    'delete' => array(),
                )
            ));
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('uuid')
            ->add('title')
            ->add('menuPriority');
    }

    /**
     * @param RouteCollection $collection
     */
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->clearExcept(['list', 'edit', 'delete', 'show']);

        parent::configureRoutes($collection);
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('title', TextType::class, ['required' => true])
            ->add('menuPriority', IntegerType::class, ['label' => self::MENU_PRIORITY_LABEL]);
    }
}