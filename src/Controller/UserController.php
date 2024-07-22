<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\UserRegisterType;
use App\Entity\User;
use App\Entity\Photo;
use App\Repository\PhotoRepository;
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
  private PhotoRepository $photoRepository;

  public function __construct(UserRepository $userRepository, PhotoRepository $photoRepository)
  {
    $this->userRepository = $userRepository;
    $this->photoRepository = $photoRepository;
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
          new OA\Property(property: 'avatar', type: 'string', format: 'string'),
          new OA\Property(property: 'photos', type: 'array', items: new OA\Items(
            type: 'string',
            format: 'object',
            properties: [
              new OA\Property(property: 'name', type: 'string', format: 'string'),
              new OA\Property(property: 'url', type: 'string', format: 'string'),
            ]
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
    $json = json_decode($request->getContent(), true);

    if (empty($json['email']) || empty($json['password']) || empty($json['firstName']) || empty($json['lastName'])) {
      return $this->json(['error' => 'Missing required fields'], 400);
    }

    if (strlen($json['firstName']) < 2 || strlen($json['lastName']) < 2) {
      return $this->json(['error' => 'First and last name must have at least 2 characters'], 400);
    }

    if (strlen($json['password']) < 6) {
      return $this->json(['error' => 'Password must have at least 6 characters'], 400);
    }

    if (strlen($json['firstName']) > 25 || strlen($json['lastName']) > 25) {
      return $this->json(['error' => 'First and last name must have a maximum of 25 characters'], 400);
    }

    if (strlen($json['password']) > 50) {
      return $this->json(['error' => 'Password must have a maximum of 25 characters'], 400);
    }

    if ($this->userRepository->findOneBy(['email' => $json['email']])) {
      return $this->json(['error' => 'Email already registered'], 400);
    }

    if (count($json['photos']) < 4) {
      return $this->json(['error' => 'At least 4 photos are required'], 400);
    }

    $photos = [];
    foreach ($json['photos'] as $photoData) {
      try {
        $photo = new Photo();
        $photo->setName($photoData['name']);
        $photo->setUrl($photoData['url']);
        $photo->setUser($user);
        $photo->setCreatedAt(new \DateTimeImmutable());
        $photo->setUpdatedAt(new \DateTimeImmutable());
        $photos[] = $photo;
      } catch (FileException $e) {
        return $this->json(['error' => 'Error uploading photos'], 500);
      }
    }

    if (empty($json['avatar'])) {
      $json['avatar'] = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($json['email'])));
    }

    $user->setFirstName($json['firstName']);
    $user->setLastName($json['lastName']);
    $user->setEmail($json['email']);
    $user->setPassword($encoder->hashPassword($user, $json['password']));
    $user->setAvatar($json['avatar']);
    $user->setActive(true);
    $user->setCreatedAt(new \DateTimeImmutable());
    $user->setUpdatedAt(new \DateTimeImmutable());

    try {
      $this->userRepository->save($user);
    } catch (\Exception $e) {
      return $this->json(['error' => 'Error saving user', 'message' => $e->getMessage()], 500);
    }

    try {
      foreach ($photos as $photo) {
        $this->photoRepository->save($photo);
      }
    } catch (\Exception $e) {
      return $this->json(['error' => 'Error saving photos', 'message' => $e->getMessage()], 500);
    }

    return $this->json(['userId' => $user->getId()], 201);
  }
}
