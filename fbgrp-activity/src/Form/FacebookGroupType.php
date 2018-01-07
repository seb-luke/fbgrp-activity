<?php

namespace App\Form;

use App\Entity\FacebookGroups;
use App\Exceptions\WarriorException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FacebookGroupType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @throws WarriorException
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $fbPageChoices = $this->extractFbPageChoicesFromOptions($options);
        $fbGroupChoices = $this->extractFbGroupChoicesFromOptions($options);

        $builder
            ->add('fbPageId', ChoiceType::class, [
                'choices' => $fbPageChoices,
                'label' =>  'Select (if wanted) which Facebook Page should be linked as an admin of the group',
            ])
            ->add('secondaryGroupId', ChoiceType::class, [
                'choices' => $fbGroupChoices,
                'label' =>  'Select (if any) which Facebook Group is the secondary group',
            ])
            ->add('save', SubmitType::class, ['label' => 'Create Warriors Group'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => FacebookGroups::class,
            'managed_pages' => null,
            'groups' => null,
            'current_group_id' => null
        ]);
    }

    /**
     * @param array $options
     * @return array
     */
    private function extractFbPageChoicesFromOptions(array $options)
    {
        $managedPages = $options['managed_pages'];
        $fbPageChoices = ['Do not link page' => null];

        if ($managedPages != null) {
            foreach ($managedPages as $managedPage) {
                $fbPageChoices[$managedPage['name']] = $managedPage['id'];
            }
        }

        return $fbPageChoices;
    }

    /**
     * @param array $options
     * @return array
     * @throws WarriorException
     */
    private function extractFbGroupChoicesFromOptions(array $options)
    {
        if ($options['current_group_id'] == null) {
            throw new WarriorException("Current group ID should be set when generating the form.");
        }

        $groups = $options['groups'];
        $fbGroupChoices = ['Do not set secondary group' => null];

        if ($groups != null) {
            foreach ($groups as $group) {
                if ($group['id'] != $options['current_group_id']) {
                    $fbGroupChoices[$group['name']] = $group['id'];
                }
            }
        }

        return $fbGroupChoices;
    }
}
