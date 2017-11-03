<?php

namespace Dfn\Bundle\OroCronofyBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CronofyAuthButtonType
 * @package Dfn\Bundle\OroCronofyBundle\Form\Type
 */
class CronofyAuthButtonType extends ButtonType
{
    const NAME = 'dfn_oro_cronofy_auth_button';

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['attr' => ['class' => 'btn btn-primary']]);
    }
}
