<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserType extends AbstractType
{
    private Security $security;
    private TranslatorInterface $translator;

    public function __construct(Security $security, TranslatorInterface $translator)
    {
        $this->security = $security;
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'form.name',
            'constraints' => [new NotBlank()],
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            /** @var User $user */
            $user = $event->getData();

            if ($this->security->isGranted(User::ROLE_ADMIN)) {
                $form->add('roles', ChoiceType::class, [
                    'label' => 'form.roles',
                    'choices' => User::MANAGED_ROLES,
                    'choice_label' => fn ($choice) => 'role.'.$choice,
                    'choice_attr' => function ($choice) use ($user) {
                        // Prevent the current user from removing their admin access.
                        if ($choice === User::ROLE_ADMIN && $user === $this->security->getUser()) {
                            return ['disabled' => true];
                        }

                        return [];
                    },
                    'expanded' => true,
                    'multiple' => true,
                ]);
            }

            $form->add('contactEmail', ChoiceType::class, [
                'label' => 'form.contact_email',
                'choices' => $user->getEmailAddresses(),
                'placeholder' => $this->translator->trans('form.none'),
                'choice_label' => fn ($choice) => $choice,
                'choice_translation_domain' => false,
                'required' => false,
                'expanded' => true,
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            if (!$event->getForm()->has('roles')) {
                return;
            }

            /** @var User $user */
            $user = $event->getData();

            if ($user !== $this->security->getUser()) {
                return;
            }

            if (!$this->security->isGranted(User::ROLE_ADMIN)) {
                return;
            }

            $roles = $user->getRoles();
            if (!\in_array(User::ROLE_ADMIN, $roles, true)) {
                $roles[] = User::ROLE_ADMIN;

                $user->setRoles($roles);
            }

            $event->setData($user);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
