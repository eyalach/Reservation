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
        // On récupère l'objet User pour savoir si on est en création ou modification
        $user = $options['data'];
        $isNew = $user->getId() === null;

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
                'required' => $isNew, // OBLIGATOIRE seulement si c'est un nouvel utilisateur
                'first_options'  => [
                    'label' => 'Mot de passe',
                    'attr' => ['class' => 'form-control'],
                    'help' => !$isNew ? 'Laissez vide pour ne pas modifier le mot de passe actuel.' : ''
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['class' => 'form-control']
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Client'         => 'ROLE_USER',
                    'Serveur'        => 'ROLE_SERVEUR',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => true,
                'multiple' => false,
                'label' => 'Rôle',
                'label_attr' => ['class' => 'fw-bold mt-2']
            ]);

        // Transformer pour gérer le champ roles
        $builder->get('roles')
            ->addModelTransformer(new CallbackTransformer(
                function ($rolesArray) {
                    // S'assure que c'est un tableau avant de filtrer
                    $rolesArray = (array) $rolesArray;
                    $rolesArray = array_filter($rolesArray, fn($r) => $r !== 'ROLE_USER');
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
