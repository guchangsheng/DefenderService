<?phpnamespace DefenderService\DamMicroService;use Illuminate\Support\Facades\Facade;class  DamMicroService extends Facade{    protected static function getFacadeAccessor()    {        return 'Service';    }}