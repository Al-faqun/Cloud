<?php
namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class NewFolderForm extends AbstractType
{
    public $fileName = '';
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('fileName', TextType::class, [
                'mapped' => false,
                'label' => 'Название папки/файла',
                'constraints' => [
                    new NotBlank(),
                    new Length([
                        'min' => 3,
                        'max' => 200,
                    ]),
                    new Regex([
                        'pattern' => '~[/\:;=*?"<>|]+~',
                        'match'   => false,
                        'message' => 'Название папки/файла содержит запрещенные символы',
                    ])
                ],
            ]);
         
    }
}