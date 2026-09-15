<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\TopicRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController extends AbstractController
{

    #[Route('/categorie/{id}', name: 'app_category_show')]
    public function show(Category $category, TopicRepository $topicRepository): Response
    {
        return $this->render('front/category/show.html.twig', [
            'category' => $category,
            'topics' => $topicRepository->findByCategory($category),
        ]);
    }

}
