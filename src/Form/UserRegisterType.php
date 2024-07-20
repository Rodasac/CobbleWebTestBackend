<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UserRegisterType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
    $builder
      ->add('email', TextType::class)
      ->add('password', PasswordType::class)
      ->add('firstName', TextType::class)
      ->add('lastName', TextType::class)
      ->add('avatar', FileType::class, [
        'mapped' => false,
        'required' => false,
      ])
      ->add('photos', FileType::class, [
        'mapped' => false,
        'multiple' => true,
        'required' => false,
      ]);
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefaults([
      'data_class' => User::class,
    ]);
  }
}
