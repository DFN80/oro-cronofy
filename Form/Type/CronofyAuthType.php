<?php

namespace Dfn\Bundle\OroCronofyBundle\Form\Type;

use Dfn\Bundle\OroCronofyBundle\Manager\CronofyOauth2Manager;

use Doctrine\Common\Persistence\ManagerRegistry;

use Symfony\Component\Form\Extension\Core\Type\BaseType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class CronofyAuthType
 * @package Dfn\Bundle\OroCronofyBundle\Form\Type
 */
class CronofyAuthType extends BaseType
{
    const NAME = 'dfn_oro_cronofy_auth';

    /** @var CronofyOauth2Manager  */
    protected $oauth2Manager;

    /** @var ManagerRegistry */
    private $doctrine;

    /** @var TokenStorageInterface */
    private $securityToken;

    /**
     * CronofyAuthType constructor.
     * @param CronofyOauth2Manager $oauth2Manager
     * @param ManagerRegistry $doctrine
     * @param TokenStorageInterface $securityToken
     */
    public function __construct(
        CronofyOauth2Manager $oauth2Manager,
        ManagerRegistry $doctrine,
        TokenStorageInterface $securityToken
    ) {
        $this->oauth2Manager = $oauth2Manager;
        $this->doctrine = $doctrine;
        $this->securityToken = $securityToken;
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

        $repo = $this->doctrine->getRepository('DfnOroCronofyBundle:CalendarOrigin');

        //Load active calendar origin for current user if one
        $calendarOrigin = $repo->findOneBy(
            [
                'owner' => $this->securityToken->getToken()->getUser(),
                'isActive' => true
            ]
        );

        $view->vars = array_merge($view->vars, [
            'connectUrl' => $this->oauth2Manager->getAuthorizationUrl(),
            'calendarOrigin' => $calendarOrigin
        ]);
    }
}
