<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

class RegistrationController extends AbstractController
{
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, LoginFormAuthenticator $authenticator): Response
    {
        $errors = [];
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $user = new User();
            
            // encode the plain password
            $user->setPassword(
                $passwordEncoder->encodePassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            
            $username = $form->get('username')->getData();
            $userFolder = User::cleanse($username);
            if ($entityManager->getRepository(User::class)->checkFolderExists($userFolder)
                OR ($entityManager->getRepository(User::class)->findBy(['username' => $username]))
            ) {
                $errors[] = 'Такой пользователь уже существует, попробуйте другое имя';
            } else {
                $user->setUsername($form->get('username')->getData());
                $user->setDirectory($userFolder);
                
                $entityManager->persist($user);
                $entityManager->flush();
                
    
                return $guardHandler->authenticateUserAndHandleSuccess(
                    $user,
                    $request,
                    $authenticator,
                    'main' // firewall name in security.yaml
                );
            }
            
        }
        
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'user' => $this->getUser(),
            'errors' => $errors,
            'messages' => []
        ]);
    }
}
