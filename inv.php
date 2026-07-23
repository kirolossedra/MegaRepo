<?php

declare(strict_types=1);

const DATA_FILE = __DIR__ . '/inventory_data.json';

final class Product
{
    public function __construct(
        public int $id,
        public string $name,
        public string $category,
        public int $quantity,
        public float $price
    ) {
    }

    public function getValue(): float
    {
        return $this->quantity * $this->price;
    }

    public function isLowStock(int $threshold = 5): bool
    {
        return $this->quantity <= $threshold;
    }

    public function matches(string $query): bool
    {
        $text = strtolower($this->name . ' ' . $this->category);

        return str_contains($text, strtolower($query));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'quantity' => $this->quantity,
            'price' => $this->price,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? ''),
            (string) ($data['category'] ?? ''),
            (int) ($data['quantity'] ?? 0),
            (float) ($data['price'] ?? 0.0)
        );
    }
}

final class Inventory
{
    private array $products = [];

    private int $nextId = 1;

    public function load(): void
    {
        if (!file_exists(DATA_FILE)) {
            return;
        }

        $json = file_get_contents(DATA_FILE);

        if ($json === false || trim($json) === '') {
            return;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new RuntimeException('Inventory data is invalid.');
        }

        $this->nextId = (int) ($data['next_id'] ?? 1);

        foreach ($data['products'] ?? [] as $productData) {
            if (is_array($productData)) {
                $this->products[] = Product::fromArray($productData);
            }
        }

        if ($this->nextId < 1) {
            $this->nextId = $this->calculateNextId();
        }
    }

    public function save(): void
    {
        $data = [
            'next_id' => $this->nextId,
            'products' => array_map(
                static fn(Product $product): array => $product->toArray(),
                $this->products
            ),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new RuntimeException('Could not encode inventory data.');
        }

        $temporaryFile = DATA_FILE . '.tmp';

        if (file_put_contents($temporaryFile, $json) === false) {
            throw new RuntimeException('Could not write inventory data.');
        }

        if (!rename($temporaryFile, DATA_FILE)) {
            throw new RuntimeException('Could not replace inventory data.');
        }
    }

    public function add(
        string $name,
        string $category,
        int $quantity,
        float $price
    ): Product {
        $product = new Product(
            $this->nextId,
            trim($name),
            trim($category),
            $quantity,
            $price
        );

        $this->products[] = $product;
        $this->nextId++;

        return $product;
    }

    public function all(): array
    {
        $products = $this->products;

        usort(
            $products,
            static fn(Product $a, Product $b): int =>
                strcasecmp($a->name, $b->name)
        );

        return $products;
    }

    public function find(int $id): ?Product
    {
        foreach ($this->products as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        return null;
    }

    public function remove(int $id): bool
    {
        foreach ($this->products as $index => $product) {
            if ($product->id === $id) {
                array_splice($this->products, $index, 1);

                return true;
            }
        }

        return false;
    }

    public function search(string $query): array
    {
        return array_values(
            array_filter(
                $this->products,
                static fn(Product $product): bool =>
                    $product->matches($query)
            )
        );
    }

    public function lowStock(int $threshold = 5): array
    {
        return array_values(
            array_filter(
                $this->products,
                static fn(Product $product): bool =>
                    $product->isLowStock($threshold)
            )
        );
    }

    public function totalValue(): float
    {
        return array_reduce(
            $this->products,
            static fn(float $total, Product $product): float =>
                $total + $product->getValue(),
            0.0
        );
    }

    public function count(): int
    {
        return count($this->products);
    }

    private function calculateNextId(): int
    {
        $maximumId = 0;

        foreach ($this->products as $product) {
            $maximumId = max($maximumId, $product->id);
        }

        return $maximumId + 1;
    }
}

final class InventoryApp
{
    private Inventory $inventory;

    public function __construct()
    {
        $this->inventory = new Inventory();
        $this->inventory->load();
    }

    public function run(): void
    {
        $this->printBanner();

        while (true) {
            $this->printMenu();
            $choice = $this->prompt('Choose an option');

            switch ($choice) {
                case '1':
                    $this->addProduct();
                    break;

                case '2':
                    $this->listProducts();
                    break;

                case '3':
                    $this->searchProducts();
                    break;

                case '4':
                    $this->updateQuantity();
                    break;

                case '5':
                    $this->removeProduct();
                    break;

                case '6':
                    $this->showLowStock();
                    break;

                case '7':
                    $this->showStatistics();
                    break;

                case '8':
                    $this->saveAndExit();

                    return;

                default:
                    echo "Invalid option. Enter a number from 1 to 8.\n";
            }
        }
    }

    private function printBanner(): void
    {
        echo "\n";
        echo str_repeat('=', 50) . "\n";
        echo "             PHP INVENTORY MANAGER\n";
        echo str_repeat('=', 50) . "\n";
    }

    private function printMenu(): void
    {
        echo "\n";
        echo "1. Add product\n";
        echo "2. List products\n";
        echo "3. Search products\n";
        echo "4. Update quantity\n";
        echo "5. Remove product\n";
        echo "6. Show low-stock products\n";
        echo "7. Show statistics\n";
        echo "8. Save and exit\n";
        echo "\n";
    }

    private function prompt(string $message): string
    {
        echo $message . ': ';

        $input = fgets(STDIN);

        if ($input === false) {
            return '';
        }

        return trim($input);
    }

    private function requiredPrompt(string $message): string
    {
        while (true) {
            $value = $this->prompt($message);

            if ($value !== '') {
                return $value;
            }

            echo "This value cannot be empty.\n";
        }
    }

    private function readInteger(string $message): ?int
    {
        $value = $this->prompt($message);

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            echo "Please enter a valid integer.\n";

            return null;
        }

        return (int) $value;
    }

    private function readFloat(string $message): ?float
    {
        $value = $this->prompt($message);

        if (!is_numeric($value)) {
            echo "Please enter a valid number.\n";

            return null;
        }

        return (float) $value;
    }

    private function addProduct(): void
    {
        $name = $this->requiredPrompt('Product name');
        $category = $this->requiredPrompt('Category');
        $quantity = $this->readInteger('Quantity');
        $price = $this->readFloat('Unit price');

        if ($quantity === null || $price === null) {
            return;
        }

        if ($quantity < 0 || $price < 0) {
            echo "Quantity and price cannot be negative.\n";

            return;
        }

        $product = $this->inventory->add(
            $name,
            $category,
            $quantity,
            $price
        );

        $this->inventory->save();

        echo "Added product #{$product->id}: {$product->name}\n";
    }

    private function listProducts(): void
    {
        $products = $this->inventory->all();

        if ($products === []) {
            echo "The inventory is empty.\n";

            return;
        }

        foreach ($products as $product) {
            $this->printProduct($product);
        }

        echo count($products) . " product(s) shown.\n";
    }

    private function searchProducts(): void
    {
        $query = $this->requiredPrompt('Search name or category');
        $products = $this->inventory->search($query);

        if ($products === []) {
            echo "No products matched the search.\n";

            return;
        }

        foreach ($products as $product) {
            $this->printProduct($product);
        }
    }

    private function updateQuantity(): void
    {
        $id = $this->readInteger('Product ID');

        if ($id === null) {
            return;
        }

        $product = $this->inventory->find($id);

        if ($product === null) {
            echo "Product #{$id} was not found.\n";

            return;
        }

        $quantity = $this->readInteger('New quantity');

        if ($quantity === null || $quantity < 0) {
            echo "Quantity must be zero or greater.\n";

            return;
        }

        $product->quantity = $quantity;
        $this->inventory->save();

        echo "Updated quantity for {$product->name}.\n";
    }

    private function removeProduct(): void
    {
        $id = $this->readInteger('Product ID');

        if ($id === null) {
            return;
        }

        $product = $this->inventory->find($id);

        if ($product === null) {
            echo "Product #{$id} was not found.\n";

            return;
        }

        $confirmation = strtolower(
            $this->prompt("Delete {$product->name}? Enter yes")
        );

        if ($confirmation !== 'yes') {
            echo "Deletion cancelled.\n";

            return;
        }

        $this->inventory->remove($id);
        $this->inventory->save();

        echo "Removed product #{$id}.\n";
    }

    private function showLowStock(): void
    {
        $threshold = $this->readInteger('Low-stock threshold');

        if ($threshold === null || $threshold < 0) {
            echo "Threshold must be zero or greater.\n";

            return;
        }

        $products = $this->inventory->lowStock($threshold);

        if ($products === []) {
            echo "No low-stock products found.\n";

            return;
        }

        foreach ($products as $product) {
            $this->printProduct($product);
        }
    }

    private function showStatistics(): void
    {
        echo "\n";
        echo "Inventory statistics\n";
        echo "--------------------\n";
        echo "Products: " . $this->inventory->count() . "\n";
        echo "Total value: $" .
            number_format($this->inventory->totalValue(), 2) .
            "\n";
        echo "Low stock: " .
            count($this->inventory->lowStock()) .
            "\n";
    }

    private function printProduct(Product $product): void
    {
        echo "\n";
        echo "#{$product->id} {$product->name}\n";
        echo "Category: {$product->category}\n";
        echo "Quantity: {$product->quantity}\n";
        echo "Unit price: $" . number_format($product->price, 2) . "\n";
        echo "Stock value: $" .
            number_format($product->getValue(), 2) .
            "\n";

        if ($product->isLowStock()) {
            echo "Status: LOW STOCK\n";
        } else {
            echo "Status: In stock\n";
        }
    }

    private function saveAndExit(): void
    {
        $this->inventory->save();

        echo "Inventory saved.\n";
        echo "Goodbye!\n";
    }
}

try {
    $app = new InventoryApp();
    $app->run();
} catch (Throwable $error) {
    fwrite(
        STDERR,
        "Application error: {$error->getMessage()}\n"
    );

    exit(1);
}
