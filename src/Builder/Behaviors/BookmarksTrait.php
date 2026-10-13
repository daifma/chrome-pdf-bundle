<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;

/**
 * Requires qpdf.
 *
 * @phpstan-type Bookmark array{title: string, page: int, children?: list<array{title: string, page: int, children?: list<mixed>}>}
 *
 * @package Behavior\\Bookmarks
 */
trait BookmarksTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Bookmarks to write. When provided as a list, it is applied directly to the final merged PDF.
     * When provided as a map of filename to bookmarks, page indexes are shifted per file before merging.
     * The `children` property allows nesting bookmarks to create a hierarchical table of contents.
     *
     * @param list<Bookmark>|array<string, list<Bookmark>> $bookmarks
     *
     * @example bookmarks([['title' => 'Introduction', 'page' => 1, 'children' => [['title' => 'Overview', 'page' => 1]]], ['title' => 'Appendix', 'page' => 5]])
     * @example bookmarks(['1_pdf.pdf' => [['title' => 'Introduction', 'page' => 1]], '2_pdf.pdf' => [['title' => 'Appendix', 'page' => 1]]])
     */
    public function bookmarks(array $bookmarks): static
    {
        if (array_is_list($bookmarks)) {
            ValidatorFactory::bookmarks($bookmarks);
        } else {
            foreach ($bookmarks as $fileBookmarks) {
                ValidatorFactory::bookmarks($fileBookmarks);
            }
        }

        if ([] === $bookmarks) {
            $this->getBodyBag()->unset('bookmarks');
        } else {
            $this->getBodyBag()->set('bookmarks', $bookmarks);
        }

        return $this;
    }

    /**
     * Adds a single bookmark entry to the existing list.
     * The `children` property allows nesting bookmarks to create a hierarchical table of contents.
     *
     * @param list<Bookmark> $children
     *
     * @example addBookmark('Introduction', 1)
     * @example addBookmark('Chapter 1', 1, [['title' => 'Overview', 'page' => 1]])
     */
    public function addBookmark(string $title, int $page, array $children = []): static
    {
        ValidatorFactory::page($page);
        ValidatorFactory::bookmarks($children);

        /** @var list<Bookmark> $current */
        $current = $this->getBodyBag()->get('bookmarks', []);

        $bookmark = ['title' => $title, 'page' => $page];
        if ([] !== $children) {
            $bookmark['children'] = $children;
        }

        $this->getBodyBag()->set('bookmarks', [...$current, $bookmark]);

        return $this;
    }

    /**
     * Extracts existing bookmarks from input files and offsets their page numbers
     * based on their position in the merged document (default false).
     *
     * @example autoIndexBookmarks() // is same as `->autoIndexBookmarks(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('auto_index_bookmarks'))]
    public function autoIndexBookmarks(bool $bool = true): static
    {
        $this->getBodyBag()->set('autoIndexBookmarks', $bool);

        return $this;
    }
}
