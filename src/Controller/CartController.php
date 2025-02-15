<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderDetails;
use App\Repository\ArticleRepository;
use App\Repository\OrderDetailsRepository;
use App\Service\EmailService;
use App\Service\PdfGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

final class CartController extends AbstractController
{
    #[Route('/cart', name: 'app_cart')]
    public function index(Request $request): Response
    {
        // recupere la session envoyer avec la requete
        $session = $request->getSession();
        //stock dans une variable la session cart
        $cartSession = $session->get('cart');
        //initialise la variable total_amount a 0
        $total_amount = 0;

        // si la session cart n'est pas null et que le nombre d'articles est superieur a 0
        if (!is_null($cartSession) && count($cartSession["id"]) > 0) {
            //boucle sur le nombre d'articles
            for ($i = 0; $i < count($cartSession["id"]); $i++) {
                //calcule le total du panier
                $total_amount += $cartSession["price"][$i] * $cartSession["quantity"][$i];
            }

        }

        //retourne la vue qui affiche le panier
        return $this->render('cart/index.html.twig', [
            //nom et valeur des variables a envoyer a la vue
            'cart_items' => $cartSession,
            'total_amount' => $total_amount
        ]);
    }

    #[Route('/cart/{idArticle}', name: 'app_add_cart', methods: ['POST'])]
    public function addArticleToCart(int $idArticle, Request $request, ArticleRepository $articleRepository): Response
    {

        // créer la session
        $session = $request->getSession();

        // si elle existe pas je la créé
        if (!$session->get('cart')) {
            $session->set('cart', [
                "id" => [],
                "title" => [],
                "description" => [],
                "stock" => [],
                "price" => [],
                "picture" => [],
                "quantity" => [],
            ]);
        }
        // je la récupère
        $cartSession = $session->get('cart');

        // je récupère les infos du produit en bdd que je souhaite ajouter à mon panier
        $product = $articleRepository->find($idArticle);

        // j'alimente ma sessions panier avec les infos du produit

        $cartSession["id"][] = $product->getId();
        $cartSession["title"][] = $product->getTitle();
        $cartSession["description"][] = $product->getDescription();
        $cartSession["stock"][] = $product->getStock();
        $cartSession["price"][] = $product->getPrice();
        $cartSession["picture"][] = $product->getPicture();
        $cartSession["quantity"][] = 1;

        // mettre à jour la session
        $session->set('cart', $cartSession);

        //redirect to the cart page
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/delete/{idArticle}', name: 'app_delete_cart')]
    public function deleteProductFromCart(int $idArticle, Request $request, ArticleRepository $articleRepository): Response
    {
        // récupérer la session
        $session = $request->getSession();
        $cartSession = $session->get('cart', []);

        // Vérifier si l'article existe dans le panier
        if (($key = array_search($idArticle, $cartSession['id'])) !== false) {
            // Supprimer toutes les informations liées à cet article
            unset($cartSession['id'][$key]);
            unset($cartSession['title'][$key]);
            unset($cartSession['description'][$key]);
            unset($cartSession['stock'][$key]);
            unset($cartSession['price'][$key]);
            unset($cartSession['picture'][$key]);
            unset($cartSession['quantity'][$key]);

            // Réindexer les tableaux pour éviter les trous dans les clés
            $cartSession['id'] = array_values($cartSession['id']);
            $cartSession['title'] = array_values($cartSession['title']);
            $cartSession['description'] = array_values($cartSession['description']);
            $cartSession['stock'] = array_values($cartSession['stock']);
            $cartSession['price'] = array_values($cartSession['price']);
            $cartSession['picture'] = array_values($cartSession['picture']);
            $cartSession['quantity'] = array_values($cartSession['quantity']);
        }

        // Mettre à jour la session avec le nouveau panier
        $session->set('cart', $cartSession);

        // Rediriger vers la page du panier
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/delete/', name: 'app_delete_all_cart')]
    public function deleteALLProductFromCart(Request $request, ArticleRepository $articleRepository): Response
    {
        // récupérer la session
        $session = $request->getSession();
        $cartSession = $session->get('cart', []);



        // Mettre à jour la session avec le nouveau panier
        $session->remove('cart', $cartSession);

        // Rediriger vers la page du panier
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/modify/quantity', name: 'app_modify_cart', methods: ['POST'])]
    public function modifyQuantityCart(Request $request, ArticleRepository $articleRepository): Response
    {

        // récupérer la valeur des paramètres post depuis un formulaire
        $idArticle = $request->request->get('idArticle');
        $quantity = $request->request->get('quantity');

        // récupérer la session
        $session = $request->getSession();
        $cartSession = $session->get('cart', []);
        // Vérifier si l'article existe dans le panier et stocker sa clé
        if (($key = array_search($idArticle, $cartSession['id'])) !== false) {

            // mettre à jour la quantité par rapport a sa position dans le tableau
            $cartSession['quantity'][$key] = $quantity;

            // mettre à jour la session
            $session->set('cart', $cartSession);

            $this->addFlash('success', 'La quantité a bien été modifié !');

        }

        // Rediriger vers la page du panier
        return $this->redirectToRoute('app_cart');

    }

    #[Route('cart/controller/payment', name: 'app_validate_cart', methods: ['POST'])]
    public function validateCart(
        Request $request,
        ArticleRepository $articleRepository,
        OrderDetailsRepository $orderDetailsRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        PdfGeneratorService $pdfGeneratorService,
        EmailService $emailService,
    ): Response {

        // récupérer la valeur des paramètres post depuis un formulaire
        $totalAmount = $request->request->get('total_amount');

        // récupérer la session
        $session = $request->getSession();
        $cartSession = $session->get('cart', []);

        if (!is_null($cartSession) && count($cartSession["id"]) > 0) {

            $order = new Order();
            $order->setAmount($totalAmount);
            $order->setDate(new \DateTime());
            $order->setStatus("En cours");
            $entityManager->persist($order);

            for ($i = 0; $i < count($cartSession["id"]); $i++) {
                $order_detail = new OrderDetails();

                $product = $articleRepository->find($cartSession["id"][$i]); // je récupère le produit en bdd pour le lier au détail de commande
                $order_detail->setArticle($product);
                $order_detail->setQuantity($cartSession["quantity"][$i]);
                $order_detail->setRelatedOrder($order);
                $order_detail->setSubtotal($cartSession["quantity"][$i] * $cartSession["price"][$i]);
                $entityManager->persist($order_detail);

            }

            $entityManager->flush();

            $fileName =  $this->getUser()->getId() . "_" . $this->getUser()->getEmail() . "_invoice" . $order->getId() . ".pdf";
            
            $invoicePDF = $pdfGeneratorService->generatePdf(            [
                'user' => $this->getUser(),
                'total_amount' => $totalAmount,
                'date' => new \DateTime(),
                'order_details' => $orderDetailsRepository->findBy(['relatedOrder' => $order->getId()])
            ], $fileName, 'invoice/index.html.twig', 'uploads/invoices');

            $emailService->sendEmail(
                $this->getUser()->getEmail(), 
                $invoicePDF, sprintf('facture-' . $order->getId() . 'ecosymfony.pdf'),
                [
                    'user' => $this->getUser(),
                    'total_amount' => $totalAmount,
                    'date' => new \DateTime(),
                    'order_details' => $orderDetailsRepository->findBy(['relatedOrder' => $order->getId()]),
                    'order' => $order
                ], 
                "Merci pour votre achat !", 
                "invoice/email.html.twig");

        }

        $this->addFlash('success', 'La commande a bien été effectuée !');

        // Rediriger vers la page du panier
        return $this->redirectToRoute('app_profile');

    }


}
