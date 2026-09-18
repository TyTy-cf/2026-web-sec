<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Topic;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TopicType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'form.topic_title_label',
                'constraints' => [
                    new NotBlank(),
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'form.topic_content_label',
                'constraints' => [
                    new NotBlank(),
                ],
                'attr' => [
                    'rows' => 8,
                    'class' => 'form-control',
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'form.topic_category_label',
                'constraints' => [
                    new NotBlank(),
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('picture', FileType::class, [
                'label' => 'form.topic_picture_label',
                'mapped' => false,
                'required' => $options['isNew'],
                'help' => $options['isNew'] ? null : 'form.topic_picture_help',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => $options['isNew'] ? 'form.create_topic' : 'form.save_changes',
                'attr' => [
                    'class' => 'btn btn-success mt-2',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Topic::class,
            'isNew' => true,
        ]);

        $resolver->setAllowedTypes('isNew', 'bool');
    }
}
