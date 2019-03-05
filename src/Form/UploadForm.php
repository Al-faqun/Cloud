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
use Tiloweb\Base64Bundle\Form\Base64Type;

class UploadForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('fileName', TextType::class, [
                'mapped' => false,
                'label' => 'Название файла (если пустое, берётся текущее)',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 200,
                    ]),
                    new Regex([
                        'pattern' => '~[/\:;=*?"<>|]+~',
                        'match'   => false,
                        'message' => 'Название файла содержит запрещенные символы',
                    ])
                ],
            ]);
    }
}