<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController extends AbstractController
{
    #[Route('/article', name: 'app_article')]
    public function index(): Response
    {
        // retourne la vue qui affiche tous les articles
        return $this->render('article/index.html.twig', [
            'controller_name' => 'ArticleController',
        ]);
    }

    #[Route('/article/{id}', name: 'show_article')]
    public function getArticleInfo(int $id, ArticleRepository $articleRepository): Response
    {
        // récupérer en bdd les informations liées à l'article avec l'id = {id}
        // vas chercher l'article qui a l'id = {id}
        $article = $articleRepository->find($id);
        // retourne la vue qui affiche les informations de l'article
        return $this->render('article/index.html.twig', [
            'article' => $article,
        ]);
    }

//     #[Route('/search', name: 'app_article_search')]
// public function search(Request $request, ArticleRepository $articleRepository): Response
// {
//     $query = $request->query->get('query');

//     if (!$query) {
//         return $this->redirectToRoute('app_home');
//     }

//     $articles = $articleRepository->findByTitleOrContent($query);

//     return $this->render('article/search_results.html.twig', [
//         'articles' => $articles,
//         'query' => $query
//     ]);
// }


    // REST
    // ARTICLE

    // /article (GET) => AFFICHE TOUS MES ARTICLES
    // /article (POST) => AJOUTE UN ARTICLE EN BDD
    // /article/1 (GET) => AFFICHE MOI L'ARTICLE QUI A L'ID 1
    // /article/1 (PUT) => MODIFIE MOI L'ARTICLE QUI A L'ID 1
    // /article/1 (DELETE) => SUPPRIME MOI L'ARTICLE QUI A L'ID 1


}


