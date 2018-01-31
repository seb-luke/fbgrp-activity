<?php

namespace App\Form;

use App\Entity\FacebookGroupUsers;
use App\Entity\UsersAwaitingRemoval;
use App\Repository\UsersAwaitingRemovalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserRemovalType extends AbstractType
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choiceArray = $this->buildChoiceArray();
        
        $builder
            ->add('usersRemoval', EntityType::class, [
                'class'         =>      UsersAwaitingRemoval::class,
                'choice_label'  =>      'fullName',
                'multiple'      =>      true,
                'expanded'      =>      true,
                'query_builder' =>  function (UsersAwaitingRemovalRepository $repo) {
                    return null;    //it means find All
                }
            ])
            ->add('save', SubmitType::class, ['label' => 'Remove Selected'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // uncomment if you want to bind to a class
            //'data_class' => UserRemoval::class,
        ]);
    }

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @return FacebookGroupUsers[]
     */
    private function buildChoiceArray()
    {
        /** @var UsersAwaitingRemovalRepository $userRemovalRepo */
        $userRemovalRepo = $this->em->getRepository(UsersAwaitingRemoval::class);
        return $userRemovalRepo->findAll();
    }
}

























