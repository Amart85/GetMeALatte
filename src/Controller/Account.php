<?php

declare(strict_types=1);

namespace BuyMeACoffeeClone\Controller;

use BuyMeACoffeeClone\Kernel\Input;
use BuyMeACoffeeClone\Kernel\PhpTemplate\View;
use BuyMeACoffeeClone\Kernel\Session;
use BuyMeACoffeeClone\Service\User as UserService;
use BuyMeACoffeeClone\Service\UserSession as UserSessionService;
use BuyMeACoffeeClone\Service\UserValidation;

class Account
{
    private UserService $userService;
    private UserValidation $userValidation;
    private UserSessionService $userSessionService;
    private bool $isLoggedIn;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->userValidation = new UserValidation();
        $this->userSessionService = new UserSessionService(new Session());
        $this->isLoggedIn = $this->userSessionService->isLoggedIn();
    }

    public function signUp(): void
    {
        $viewVariables = [];

        if (Input::postExists('signup_submit')) {
            // Treat the values we receive

            $fullName = Input::post('name');
            $email = Input::post('email');
            $password = Input::post('password');

            if (isset($fullName, $email, $password)) {
                if (
                    $this->userValidation->isEmailValid($email) &&
                    $this->userValidation->isPasswordValid($password)
                ) {
                    if ($this->userService->doesAccountEmailExist($email)) {
                        $viewVariables[View::ERROR_MESSAGE_KEY] = 'An account with the same email address already exist.';
                    } else {
                        $user = [
                            'fullname' => $fullName,
                            'email' => $email,
                            'password' => $this->userService->hashPassword($password)
                        ];

                        // Register the user
                        if ($userId = $this->userService->create($user)) {
                            $this->userSessionService->setAuthentication($userId, $email, $fullName);

                            redirect();
                        } else {
                            $viewVariables[View::ERROR_MESSAGE_KEY] = 'An error while creating your account has occurred. Please try again.';
                        }
                    }
                } else {
                    $viewVariables[View::ERROR_MESSAGE_KEY] = 'Email/Password is not valid.';
                }
            } else {
                $viewVariables[View::ERROR_MESSAGE_KEY] = 'All fields are required.';
            }
        }

        View::output('account/signup', 'Sign Up', $viewVariables);
    }

    public function signIn(): void
    {
        $viewVariables = [];

        if (Input::postExists('signin_submit')) {
            $email = Input::post('email');
            $password = Input::post('password');

            $userDetails = $this->userService->getDetailsFromEmail($email);
            $isLoginValid = !empty($userDetails->password) && $this->userService->verifyPassword($password, $userDetails->password);

            if ($isLoginValid) {
                $this->userSessionService->setAuthentication($userDetails->userId, $userDetails->email, $userDetails->fullname);
                redirect();
            } else {
                $viewVariables[View::ERROR_MESSAGE_KEY] = 'Incorrect login.';
            }
        }

        View::output('account/signin', 'Sign In', $viewVariables);
    }

    public function edit(): void
    {
        $userId = $this->userSessionService->getId();
        $userDetails = $this->userService->getDetailsFromId($userId);

        $viewVariables= [
            'user' => $userDetails,
            'isLoggedIn' => $this->isLoggedIn
        ];

        if (Input::postExists('edit_submit')) {
            $name = Input::post('name');
            $email = Input::post('email');

            if (isset($name, $email)) {
                $hasEmailChanged = $email !== $userDetails->email;
                $hasNameChanged = $name !== $userDetails->fullname;

                if ($hasEmailChanged) {
                    if (!$this->userValidation->isEmailValid($email) || $this->userService->doesAccountEmailExist($email)) {
                        $viewVariables[View::ERROR_MESSAGE_KEY][] = 'Email is incorrect or it already exists';
                    } else {
                        $this->userService->updateEmail($userId, $email);
                        $viewVariables[View::SUCCESS_MESSAGE_KEY][] = 'Email has been updated.';
                    }
                }

                if ($hasNameChanged) {
                    if (!$this->userValidation->isNameValid($name)) {
                        $viewVariables[View::ERROR_MESSAGE_KEY][] = 'Name is either too short or too long.';
                    } else {
                        $this->userService->updateName($userId, $name);
                        $viewVariables[View::SUCCESS_MESSAGE_KEY][] = 'Name has been updated.';
                    }
                }
            } else {
                $viewVariables[View::ERROR_MESSAGE_KEY] = 'All fields are required.';
            }
        }

        View::output('account/edit', 'Edit Account', $viewVariables);
    }

    /**
     * Allows to edit the user's password.
     */
    public function password(): void
    {
        $viewVariables = [
            'isLoggedIn' => $this->isLoggedIn
        ];

        if (Input::postExists('password_submit')) {
            $currentPassword = Input::post('current_password');
            $newPassword = Input::post('new_password');
            $confirmPassword = Input::post('confirm_password');

            $userId = $this->userSessionService->getId();
            if ($currentPassword === $this->userService->getHashedPassword($userId)) {
                // If current user's password is valid, let's proceed
                if ($newPassword === $confirmPassword) {
                    $userId = $this->userSessionService->getId();
                    if ($this->userValidation->isPasswordValid($newPassword)) {
                        $hashedPassword = $this->userService->hashPassword($newPassword);
                        $this->userService->updatePassword($userId, $hashedPassword);
                        $viewVariables[View::SUCCESS_MESSAGE_KEY] = 'Password successfully updated.';
                    } else {
                        $viewVariables[View::ERROR_MESSAGE_KEY] = 'Password is too weak.';
                    }
                } else {
                    $viewVariables[View::ERROR_MESSAGE_KEY] = 'Your passwords didn\'t match.';
                }
            } else {
                $viewVariables[View::ERROR_MESSAGE_KEY] = 'Your current password is incorrect.';
            }
        }

        View::output('account/password', 'Edit Password', $viewVariables);
    }

    public function logout(): void
    {
        $this->userSessionService->logout();

        // Redirect the user to the index page
        redirect();
    }
}
