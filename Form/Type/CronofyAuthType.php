<?php

namespace Dfn\Bundle\OroCronofyBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\BaseType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CronofyAuthType
 * @package Dfn\Bundle\OroCronofyBundle\Form\Type
 */
class CronofyAuthType extends BaseType
{
    const NAME = 'dfn_oro_cronofy_auth';

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
}
