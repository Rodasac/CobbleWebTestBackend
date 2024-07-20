<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\UserRegisterType;
use App\Entity\User;
use App\Entity\Photo;
use App\Repository\UserRepository;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

final class UserController extends AbstractController
{
  private UserRepository $userRepository;

  public function __construct(UserRepository $userRepository)
  {
    $this->userRepository = $userRepository;
  }

  #[Route('/api/users/me', name: 'users_me', methods: ['GET'])]
  #[OA\Response(
    response: 200,
    description: 'Returns the user data',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'firstName', type: 'string'),
        new OA\Property(property: 'lastName', type: 'string'),
        new OA\Property(property: 'active', type: 'boolean'),
        new OA\Property(property: 'avatar', type: 'string'),
        new OA\Property(property: 'photos', type: 'array', items: new OA\Items(
          type: 'object',
          properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'url', type: 'string'),
            new OA\Property(property: 'createdAt', type: 'string'),
            new OA\Property(property: 'updatedAt', type: 'string'),
          ]
        )),
        new OA\Property(property: 'createdAt', type: 'string'),
        new OA\Property(property: 'updatedAt', type: 'string'),
      ]
    )
  )]
  #[OA\Tag(name: 'Users')]
  #[Security(name: 'Bearer')]
  public function me(Request $request): JsonResponse
  {
    $user = $this->getUser();
    if (!$user instanceof User) {
      return $this->json(['error' => 'Unauthorized'], 401);
    }

    return $this->json([
      'id' => $user->getId(),
      'email' => $user->getEmail(),
      'firstName' => $user->getFirstName(),
      'lastName' => $user->getLastName(),
      'active' => $user->isActive(),
      'avatar' => $user->getAvatar(),
      'photos' => array_map(fn ($photo) => [
        'id' => $photo->getId(),
        'name' => $photo->getName(),
        'url' => $photo->getUrl(),
        'createdAt' => $photo->getCreatedAt()->format(DATE_ATOM),
        'updatedAt' => $photo->getUpdatedAt()->format(DATE_ATOM),
      ], $user->getPhotos()->toArray()),
      'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
      'updatedAt' => $user->getUpdatedAt()->format(DATE_ATOM),
    ]);
  }

  #[Route('/api/users/register', name: 'users_register', methods: ['POST'])]
  #[OA\RequestBody(
    required: true,
    content: new OA\MediaType(
      mediaType: 'multipart/form-data',
      schema: new OA\Schema(
        type: 'object',
        properties: [
          new OA\Property(property: 'email', type: 'string'),
          new OA\Property(property: 'password', type: 'string'),
          new OA\Property(property: 'firstName', type: 'string'),
          new OA\Property(property: 'lastName', type: 'string'),
          new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
          new OA\Property(property: 'photos', type: 'array', items: new OA\Items(
            type: 'string',
            format: 'binary'
          )),
        ]
      )
    )
  )]
  #[OA\Response(
    response: 201,
    description: 'User registered',
    content: new OA\JsonContent(
      type: 'object',
      properties: [
        new OA\Property(property: 'id', type: 'integer'),
      ]
    )
  )]
  #[OA\Tag(name: 'Users')]
  public function register(Request $request, UserPasswordHasherInterface $encoder): JsonResponse
  {
    $user = new User();
    $form = $this->createForm(UserRegisterType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      /** @var UploadedFile $avatarFile */
      $avatarFile = $form->get('avatar')->getData();

      /** @var UploadedFile[] $photosFiles */
      $photosFiles = $form->get('photos')->getData();
      if (count($photosFiles) !== 0 && count($photosFiles) < 4) {
        return $this->json(['error' => 'You must upload at least 4 photos'], 400);
      }

      if ($avatarFile) {
        $avatarFileName = md5(uniqid()) . '.' . $avatarFile->guessExtension();
        $avatarDir = $this->getParameter('upload_dir') . '/avatars';
        try {
          $avatarFile->move($avatarDir, $avatarFileName);
        } catch (FileException $e) {
          return $this->json(['error' => 'Error uploading avatar'], 500);
        }
      } else {
        $avatarFileName = 'https://placehold.co/400x400';
      }
      $user->setAvatar($avatarFileName);

      foreach ($photosFiles as $photoFile) {
        $photoFileName = md5(uniqid()) . '.' . $photoFile->guessExtension();
        $photoDir = $this->getParameter('upload_dir') . '/photos';
        try {
          $photoFile->move($photoDir, $photoFileName);
          $photo = new Photo();
          $photo->setName($photoFileName);
          $photo->setUrl($photoDir . '/' . $photoFileName);
          $photo->setUser($user);
          $photo->setCreatedAt(new \DateTimeImmutable());
          $photo->setUpdatedAt(new \DateTimeImmutable());
        } catch (FileException $e) {
          return $this->json(['error' => 'Error uploading photos'], 500);
        }
      }

      $user->setPassword($encoder->hashPassword($user, $user->getPassword()));
      $user->setActive(true);
      $user->setCreatedAt(new \DateTimeImmutable());
      $user->setUpdatedAt(new \DateTimeImmutable());

      $this->userRepository->save($user);

      return $this->json(['id' => $user->getId()], 201);
    }
  }
}
