<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Topic;
use App\Entity\User;
use App\Form\CommentType;
use App\Form\TopicType;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TopicController extends AbstractController
{

    #[Route('/recherche', name: 'app_topic_search')]
    public function search(Request $request, TopicRepository $topicRepository): Response
    {
        $query = $request->query->get('q', '');

        return $this->render('front/topic/search.html.twig', [
            'query' => $query,
            'topics' => $topicRepository->search($query),
        ]);
    }

    #[Route('/sujets/{id}', name: 'app_topic_show')]
    public function show(
        Topic $topic,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $comment = new Comment();
        $comment->setTopic($topic);

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $author */
            $author = $this->getUser();

            if (!$author) {
                $this->addFlash('warning', 'flash.login_required');
                return $this->redirectToRoute('app_login');
            }

            $comment->setAuthor($author)
                ->setCreatedAt(new \DateTime());

            $entityManager->persist($comment);
            $entityManager->flush();

            $this->addFlash('success', 'flash.comment_posted');

            return $this->redirectToRoute('app_topic_show', ['id' => $topic->getId()]);
        }

        return $this->render('front/topic/show.html.twig', [
            'topic' => $topic,
            'comments' => $topic->getComments(),
            'commentForm' => $form,
        ]);
    }

    #[Route('/sujets/{id}/modifier', name: 'app_topic_edit')]
    public function edit(
        Topic $topic,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(TopicType::class, $topic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $topic->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'flash.topic_updated');

            return $this->redirectToRoute('app_topic_show', ['id' => $topic->getId()]);
        }

        return $this->render('front/topic/edit.html.twig', [
            'topic' => $topic,
            'topicForm' => $form,
        ]);
    }

}
