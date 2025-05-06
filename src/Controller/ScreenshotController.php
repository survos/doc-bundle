<?php

namespace Survos\DocBundle\Controller;

use App\Tests\PantherTest;
use Roave\BetterReflection\BetterReflection;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use PhpParser\Error;
use PhpParser\ParserFactory;

final class ScreenshotController extends AbstractController
{
    #[Route('/screenshots', name: 'app_screenshots')]
    #[Template('app/screenshots.html.twig')]
    public function index(): array
    {
        $reflection = new \ReflectionClass(PantherTest::class);
        $methods = [];
        $classInfo = (new BetterReflection())
            ->reflector()
            ->reflectClass(PantherTest::class);
        foreach ($classInfo->getMethods() as $method) {
            if (!str_starts_with($method->getName(), 'test')) {
                continue;
            }
            $source = explode("\n", $method->getLocatedSource()->getSource());
            $content = join("\n", array_slice($source, $method->getStartLine() + 1, $method->getEndLine() - $method->getStartLine()));
            $php = array_slice($source, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine());
            foreach (explode("\n\n", $content) as $statement) {
                $url = null;
                if (preg_match("/Screenshot\('(.*?)\'\)/", $statement, $matches)) {
                    $url = $matches[1];
                }
                $methods[$method->getName()][] = [
                    'content' => $statement,
                    'url' => $url
                ];
            }
        }
        return [
            'methods' => $methods,
        ];
    }

    /**
     * the better way is to parse the PHP into an AST, maybe someday.
     *
     * @return JsonResponse
     */
    private function astWalker()
    {
        $parser = (new ParserFactory())->createForHostVersion();

        try {
            $stmts = $parser->parse($code);
            // $stmts is an array of statement nodes
            foreach ($stmts as $stmt) {
                dump($stmt);
            }
        } catch (Error $e) {
            dd($e);
            echo 'Parse Error: ', $e->getMessage(), "\n";
        }
        dd();
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/ScreenshotController.php',
        ]);
    }
}
