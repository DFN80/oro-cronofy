<?php

namespace Dfn\Bundle\OroCronofyBundle\Form\Type;

use Dfn\Bundle\OroCronofyBundle\Manager\CronofyOauth2Manager;
use Symfony\Component\Form\Extension\Core\Type\BaseType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Class CronofyAuthType
 * @package Dfn\Bundle\OroCronofyBundle\Form\Type
 */
class CronofyAuthType extends BaseType
{
    const NAME = 'dfn_oro_cronofy_auth';

    protected $oauth2Manager;

    public function __construct(CronofyOauth2Manager $oauth2Manager)
    {
        $this->oauth2Manager = $oauth2Manager;
    }

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

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars = array_merge($view->vars, [
            'url' => $this->oauth2Manager->getAuthorizationUrl()
        ]);
    }
}
