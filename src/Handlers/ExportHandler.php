<?php

declare(strict_types=1);

namespace MoonShine\Handlers;

use Closure;
use Generator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MoonShine\Contracts\Resources\ResourceContract;
use MoonShine\Exceptions\ActionException;
use MoonShine\Jobs\ExportHandlerJob;
use MoonShine\MoonShineUI;
use MoonShine\Notifications\MoonShineNotification;
use MoonShine\Traits\WithStorage;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExportHandler extends Handler
{
    use WithStorage;

    protected ?string $icon = 'heroicons.outline.table-cells';

    protected bool $isCsv = false;

    protected bool $withConfirm = false;

    protected string $csvDelimiter = ',';

    protected ?string $filename = null;

    protected array|Closure $notifyUsers = [];

    public function csv(): static
    {
        $this->isCsv = true;

        return $this;
    }

    public function withConfirm(): static
    {
        $this->withConfirm = true;

        return $this;
    }

    public function isWithConfirm(): bool
    {
        return $this->withConfirm;
    }

    public function delimiter(string $value): static
    {
        $this->csvDelimiter = $value;

        return $this;
    }

    public function filename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    protected function getFilename(): string
    {
        return $this->hasFilename() ? $this->filename : $this->getDefaultFilename();
    }

    /**
     * @param array|Closure(static $ctx): array $ids
     */
    public function notifyUsers(array|Closure $ids): static
    {
        $this->notifyUsers = $ids;

        return $this;
    }

    public function getNotifyUsers(): array
    {
        return value($this->notifyUsers, $this);
    }

    /**
     * @throws ActionException
     * @throws IOException
     * @throws WriterNotOpenedException
     * @throws UnsupportedTypeException
     * @throws InvalidArgumentException|Throwable
     */
    public function handle(): Response
    {
        $query = collect(
            request()->query()
        )->except(['_component_name', 'page'])->toArray();

        if (! $this->hasResource()) {
            throw ActionException::resourceRequired();
        }

        $this->resolveStorage();

        $path = Storage::disk($this->getDisk())->path($this->generateFilePath());

        if ($this->isQueue()) {
            ExportHandlerJob::dispatch(
                $this->getResource()::class,
                $path,
                $query,
                $this->getDisk(),
                $this->getDir(),
                $this->getDelimiter(),
                $this->getNotifyUsers()
            );

            MoonShineUI::toast(
                __('moonshine::ui.resource.queued')
            );

            return back();
        }

        return response()->download(
            self::process(
                $path,
                $this->getResource(),
                $query,
                $this->getDisk(),
                $this->getDir(),
                $this->getDelimiter(),
                $this->getNotifyUsers()
            )
        );
    }

    public function hasFilename(): bool
    {
        return ! is_null($this->filename);
    }

    public function isCsv(): bool
    {
        return $this->isCsv;
    }

    public function getDelimiter(): string
    {
        return $this->csvDelimiter;
    }

    protected function generateFilePath(): string
    {
        $dir = $this->getDir();
        $filename = $this->getFilename();
        $ext = $this->isCsv() ? 'csv' : 'xlsx';

        return sprintf('%s/%s.%s', $dir, $filename, $ext);
    }

    protected function getDefaultFilename(): string
    {
        return $this->getResource()->uriKey() . '-' . Str::uuid();
    }

    /**
     * @throws WriterNotOpenedException
     * @throws IOException
     * @throws UnsupportedTypeException
     * @throws InvalidArgumentException|Throwable
     */
    public static function process(
        string $path,
        ResourceContract $resource,
        array $query,
        string $disk = 'public',
        string $dir = '/',
        string $delimiter = ',',
        array $notifyUsers = []
    ): string {
        // TODO fix it in 3.0
        if (app()->runningInConsole()) {
            request()->merge($query);
        }

        $items = static function (ResourceContract $resource): Generator {
            foreach ($resource->resolveQuery()->cursor() as $index => $item) {
                $row = [];

                $fields = $resource->getExportFields();

                $fields->fill($item->toArray(), $item, $index);

                foreach ($fields as $field) {
                    $row[$field->label()] = $field
                        ->rawMode()
                        ->preview();
                }

                yield $row;
            }
        };

        $fastExcel = new FastExcel($items($resource));

        if (str($path)->contains('.csv')) {
            $fastExcel->configureCsv($delimiter);
        }

        $result = $fastExcel->export($path);

        $url = str($path)
            ->remove(Storage::disk($disk)->path($dir))
            ->value();

        MoonShineNotification::send(
            trans('moonshine::ui.resource.export.exported'),
            [
                'link' => Storage::disk($disk)->url(trim($dir, '/') . $url),
                'label' => trans('moonshine::ui.download'),
            ],
            $notifyUsers,
        );

        return $result;
    }
}
