<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class BookSeeder extends Seeder
{
    /**
     * Seed a library of books with realistic content sizes.
     * Content is stored as flat text files in storage/app/books/.
     * Total character counts are computed from content length.
     */
    public function run(): void
    {
        $books = [
            [
                'title'       => 'The Art of Clean Code',
                'author'      => 'Robert C. Martin',
                'isbn'        => '978-0132350884',
                'description' => 'A handbook of agile software craftsmanship.',
                'content'     => $this->generateContent(
                    'The Art of Clean Code',
                    'Clean code is not written by following a set of rules. You don\'t become a software craftsman by learning a list of heuristics. Professionalism and craftsmanship come from values that drive disciplines.',
                    120000
                ),
            ],
            [
                'title'       => 'Design Patterns in PHP',
                'author'      => 'Gang of Four',
                'isbn'        => '978-0201633610',
                'description' => 'Elements of reusable object-oriented software with PHP examples.',
                'content'     => $this->generateContent(
                    'Design Patterns in PHP',
                    'Design patterns are recurring solutions to software design problems you find again and again in real-world application development. Patterns are about reusable designs and interactions of objects.',
                    95000
                ),
            ],
            [
                'title'       => 'Laravel: Up and Running',
                'author'      => 'Matt Stauffer',
                'isbn'        => '978-1492041214',
                'description' => 'A framework for building modern PHP apps.',
                'content'     => $this->generateContent(
                    'Laravel: Up and Running',
                    'Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling.',
                    80000
                ),
            ],
            [
                'title'       => 'Domain-Driven Design',
                'author'      => 'Eric Evans',
                'isbn'        => '978-0321125217',
                'description' => 'Tackling complexity in the heart of software.',
                'content'     => $this->generateContent(
                    'Domain-Driven Design',
                    'The heart of software is its ability to solve domain-related problems for its users. All other features, vital as they may be, support this basic purpose.',
                    145000
                ),
            ],
            [
                'title'       => 'The Pragmatic Programmer',
                'author'      => 'Andrew Hunt',
                'isbn'        => '978-0135957059',
                'description' => 'Your journey to mastery.',
                'content'     => $this->generateContent(
                    'The Pragmatic Programmer',
                    'You shouldn\'t be wedded to any particular technology, but have a broad enough background and experience base to allow you to choose good solutions in particular situations.',
                    110000
                ),
            ],
        ];

        foreach ($books as $bookData) {
            $content     = $bookData['content'];
            $contentPath = "books/book-" . str_replace(' ', '-', strtolower($bookData['isbn'])) . ".txt";

            Storage::put($contentPath, $content);

            Book::updateOrCreate(
                ['isbn' => $bookData['isbn']],
                [
                    'title'        => $bookData['title'],
                    'author'       => $bookData['author'],
                    'description'  => $bookData['description'],
                    'total_chars'  => strlen($content),
                    'file_path' => $contentPath,
                ]
            );
        }

        $this->command->info('✅ Seeded ' . count($books) . ' books successfully.');
    }

    /**
     * Generate realistic lorem-like content of a target character length.
     */
    private function generateContent(string $title, string $intro, int $targetChars): string
    {
        $paragraphs = [
            "Software development is the art of building complex systems from simple, well-defined components. Each component must serve a single purpose and communicate clearly with its neighbors through well-defined interfaces.",
            "The principles of SOLID design guide developers toward code that is maintainable, extensible, and testable. The Single Responsibility Principle tells us that a class should have one and only one reason to change.",
            "Open-Closed Principle states that software entities should be open for extension but closed for modification. This means you should be able to add new functionality without changing existing code.",
            "The Liskov Substitution Principle ensures that objects of a superclass should be replaceable with objects of a subclass without breaking the application. Subtypes must be substitutable for their base types.",
            "Interface Segregation Principle dictates that no client should be forced to depend on methods it does not use. Large interfaces should be split into smaller, more specific ones.",
            "Dependency Inversion Principle states that high-level modules should not depend on low-level modules. Both should depend on abstractions. Abstractions should not depend on details.",
            "Clean architecture separates concerns into layers, with each layer having a specific responsibility. The innermost layer contains business rules, while outer layers handle infrastructure concerns.",
            "Test-driven development encourages writing tests before writing production code. This approach leads to better design, higher confidence in the code, and a comprehensive test suite.",
            "Refactoring is the process of restructuring existing code without changing its external behavior. It improves the internal structure of the code to make it easier to understand and modify.",
            "Continuous integration and deployment pipelines automate the process of building, testing, and deploying software. This reduces the risk of integration problems and enables faster delivery.",
        ];

        $content = "# {$title}\n\n{$intro}\n\n";

        $chapterNum = 1;
        while (strlen($content) < $targetChars) {
            $content .= "\n## Chapter {$chapterNum}: Understanding the Fundamentals\n\n";
            foreach ($paragraphs as $paragraph) {
                $content .= $paragraph . "\n\n";
                if (strlen($content) >= $targetChars) {
                    break;
                }
            }
            $chapterNum++;
        }

        return substr($content, 0, $targetChars);
    }
}
