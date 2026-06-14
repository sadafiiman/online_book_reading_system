<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $sharedParagraphs = [
            "Software development is the art of building complex systems from simple, well-defined components. Each component must serve a single purpose and communicate clearly with its neighbors through well-defined interfaces.",
            "Clean architecture separates concerns into layers, with each layer having a specific responsibility. The innermost layer contains business rules, while outer layers handle infrastructure concerns.",
            "Test-driven development encourages writing tests before writing production code. This approach leads to better design, higher confidence in the code, and a comprehensive test suite.",
            "Refactoring is the process of restructuring existing code without changing its external behavior. It improves the internal structure of the code to make it easier to understand and modify.",
            "Continuous integration and deployment pipelines automate the process of building, testing, and deploying software. This reduces the risk of integration problems and enables faster delivery.",
        ];

        $books = [
            [
                'title'       => 'The Art of Clean Code',
                'author'      => 'Robert C. Martin',
                'isbn'        => '978-0132350884',
                'description' => 'A handbook of agile software craftsmanship.',
                'intro'       => 'Clean code is not written by following a set of rules. You don\'t become a software craftsman by learning a list of heuristics. Professionalism and craftsmanship come from values that drive disciplines.',
                'topic'       => [
                    "The Single Responsibility Principle tells us that a class should have one and only one reason to change. A class with multiple responsibilities becomes coupled, fragile, and harder to reuse.",
                    "Naming is one of the hardest things in software, and one of the most important. A well-named variable, function, or class communicates intent without requiring a comment to explain it.",
                    "Functions should do one thing, do it well, and do it only. Small functions that read like well-written prose are easier to test, easier to reuse, and easier to reason about.",
                ],
                'target_chars' => 120000,
            ],
            [
                'title'       => 'Design Patterns in PHP',
                'author'      => 'Gang of Four',
                'isbn'        => '978-0201633610',
                'description' => 'Elements of reusable object-oriented software with PHP examples.',
                'intro'       => 'Design patterns are recurring solutions to software design problems you find again and again in real-world application development. Patterns are about reusable designs and interactions of objects.',
                'topic'       => [
                    "The Strategy pattern defines a family of algorithms, encapsulates each one, and makes them interchangeable. It lets the algorithm vary independently from the clients that use it.",
                    "The Repository pattern mediates between the domain and data mapping layers, acting like an in-memory collection of domain objects while hiding the details of persistence.",
                    "The Decorator pattern attaches additional responsibilities to an object dynamically, providing a flexible alternative to subclassing for extending behavior.",
                ],
                'target_chars' => 95000,
            ],
            [
                'title'       => 'Laravel: Up and Running',
                'author'      => 'Matt Stauffer',
                'isbn'        => '978-1492041214',
                'description' => 'A framework for building modern PHP apps.',
                'intro'       => 'Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling.',
                'topic'       => [
                    "Eloquent provides a beautiful, simple ActiveRecord implementation for working with your database. Each database table has a corresponding Model which is used to interact with that table.",
                    "Service providers are the central place of all Laravel application bootstrapping. Your own application, as well as all of Laravel's core services, are bootstrapped via service providers.",
                    "Queues allow you to defer the processing of a time-consuming task, such as sending an email, until a later time, drastically speeding up web requests to your application.",
                ],
                'target_chars' => 80000,
            ],
            [
                'title'       => 'Domain-Driven Design',
                'author'      => 'Eric Evans',
                'isbn'        => '978-0321125217',
                'description' => 'Tackling complexity in the heart of software.',
                'intro'       => 'The heart of software is its ability to solve domain-related problems for its users. All other features, vital as they may be, support this basic purpose.',
                'topic'       => [
                    "A bounded context defines the limits of applicability of a particular model, ensuring terms and concepts have a single, unambiguous meaning within that boundary.",
                    "An aggregate is a cluster of associated objects treated as a unit for data changes, with a single entity acting as the aggregate root that enforces invariants across the cluster.",
                    "Ubiquitous language is a shared language between developers and domain experts, used consistently in code, conversation, and documentation to eliminate translation loss.",
                ],
                'target_chars' => 145000,
            ],
            [
                'title'       => 'The Pragmatic Programmer',
                'author'      => 'Andrew Hunt',
                'isbn'        => '978-0135957059',
                'description' => 'Your journey to mastery.',
                'intro'       => 'You shouldn\'t be wedded to any particular technology, but have a broad enough background and experience base to allow you to choose good solutions in particular situations.',
                'topic'       => [
                    "DRY — Don't Repeat Yourself — is about the duplication of knowledge, of intent. It means that every piece of knowledge in a system should have a single, unambiguous, authoritative representation.",
                    "Orthogonality means that changes in one component of a system should have no effect on other components. Highly orthogonal systems are easier to test and easier to change safely.",
                    "Tracer bullets let you home in on a target by trying something and seeing how close it comes, adjusting, and trying again — building a thin, working skeleton of the whole system early.",
                ],
                'target_chars' => 110000,
            ],
        ];

        foreach ($books as $bookData) {
            $paragraphs = array_merge($bookData['topic'], $sharedParagraphs);

            $content = $this->generateContent($bookData['title'], $bookData['intro'], $paragraphs, $bookData['target_chars']);

            $contentPath = 'books/book-' . str_replace([' ', '.'], ['-', ''], strtolower($bookData['isbn'])) . '.txt';
            Storage::put($contentPath, $content);

            $book = Book::updateOrCreate(
                ['isbn' => $bookData['isbn']],
                [
                    'title'       => $bookData['title'],
                    'author'      => $bookData['author'],
                    'description' => $bookData['description'],
                    'total_chars' => strlen($content),
                    'file_path'   => $contentPath,
                ]
            );

            $pages = $book->totalPagesForFontSize(config('books.default_font_size', 16));
            $this->command->line("  {$book->title}: {$book->total_chars} chars → {$pages} pages @ default font size");
        }

        $this->command->info('✅ Seeded ' . count($books) . ' books successfully.');
    }

    /**
     * Generate content by cycling through book-specific topic paragraphs
     * first, then a shared pool, repeated across chapters until the
     * target character count is reached.
     */
    private function generateContent(string $title, string $intro, array $paragraphs, int $targetChars): string
    {
        $content    = "# {$title}\n\n{$intro}\n\n";
        $chapterNum = 1;
        $paraIndex  = 0;

        while (strlen($content) < $targetChars) {
            $content .= "\n## Chapter {$chapterNum}\n\n";

            for ($i = 0; $i < count($paragraphs) && strlen($content) < $targetChars; $i++) {
                $content .= $paragraphs[$paraIndex % count($paragraphs)] . "\n\n";
                $paraIndex++;
            }

            $chapterNum++;
        }

        return substr($content, 0, $targetChars);
    }
}
