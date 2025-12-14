<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control']
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control']
            ])
            ->add('tel', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options'  => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Client'         => 'ROLE_USER',
                    'Serveur'        => 'ROLE_SERVEUR',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => true,   // boutons radio
                'multiple' => false,
                'label' => 'Rôle',

            ]);

        // Transformer pour gérer le champ roles (qui est un tableau en BDD mais un string dans le formulaire)
        $builder->get('roles')
            ->addModelTransformer(new CallbackTransformer(
                function ($rolesArray) {
                    // retirer le ROLE_USER ajouté automatiquement par getRoles()
                    $rolesArray = array_filter($rolesArray, fn($r) => $r !== 'ROLE_USER');

                    // prendre uniquement le vrai rôle choisi
                    return array_values($rolesArray)[0] ?? 'ROLE_USER';
                },
                function ($roleString) {
                    return [$roleString];
                }
            ));

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
