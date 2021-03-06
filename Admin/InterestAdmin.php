<?php
namespace Opositatest\InterestUserBundle\Admin;

use Doctrine\DBAL\Types\TextType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class InterestAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('name', null, [
                'label' => 'Nombre'
            ])
            ->add('parent', null, [
                'label' => 'Interés Padre'
            ])
            ->add('children', 'sonata_type_model', [
                'label' => 'Intereses Hijo', 'multiple' => true, 'by_reference' => false
                ], array('hide-select-from-table' => true)
            )
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper->add('id');
        $datagridMapper->add('name', null, [
            'label' => 'Nombre'
        ]);
        $datagridMapper->add('parent', null, [
            'label' => 'Interés Padre'
        ]);
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('id', null, [
                'label' => 'ID'
            ])
            ->addIdentifier('name', null, [
                'label' => 'Nombre'
            ])
            ->add('parent', null, [
                'label' => 'Interés Padre'
            ])
            ->add('children', null, [
                'label' => 'Intereses Hijo'
            ])
        ;
    }
}