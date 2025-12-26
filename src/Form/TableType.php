<?php

namespace App\Form;

use App\Entity\Table;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class TableType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numero', IntegerType::class, [
                'label' => 'Numéro de la table',
                'attr' => ['placeholder' => 'Ex: 1, 2, 3...']
            ])
            ->add('nb_places', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr' => ['min' => 1, 'max' => 20]
            ])
            ->add('estlibre', CheckboxType::class, [
                'label'    => 'Table libre ?',
                'required' => false,
                'help'     => 'Décochez si la table est libre',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Table::class,
        ]);
    }
}
