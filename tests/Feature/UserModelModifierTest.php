<?php
namespace RahatulRabbi\TalkBridge\Tests\Feature;

use Illuminate\Support\Facades\File;
use RahatulRabbi\TalkBridge\Support\UserModelModifier;
use RahatulRabbi\TalkBridge\Tests\TestCase;

class UserModelModifierTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPath = sys_get_temp_dir() . '/TalkBridgeTestUser.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPath)) {
            unlink($this->tempPath);
        }
        parent::tearDown();
    }

    public function test_injects_trait_into_fillable_model(): void
    {
        file_put_contents($this->tempPath, <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
    protected $fillable = ['name', 'email', 'password'];
}
PHP);

        $modifier = new UserModelModifier($this->tempPath);
        $modifier->inject();

        $content = file_get_contents($this->tempPath);
        $this->assertStringContainsString('@talkbridge:start', $content);
        $this->assertStringContainsString('HasTalkBridgeFeatures', $content);
        $this->assertStringContainsString("'last_seen_at'", $content);
    }

    public function test_injects_trait_into_guarded_model_without_touching_guarded(): void
    {
        file_put_contents($this->tempPath, <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
    protected $guarded = [];
}
PHP);

        $modifier = new UserModelModifier($this->tempPath);
        $modifier->inject();

        $content = file_get_contents($this->tempPath);
        $this->assertStringContainsString('@talkbridge:start', $content);
        $this->assertStringNotContainsString("'last_seen_at'", $content); // guarded=[] means no fillable change
    }

    public function test_removes_trait_cleanly(): void
    {
        $original = <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
    // @talkbridge:start
    use \RahatulRabbi\TalkBridge\Traits\HasTalkBridgeFeatures;
    // @talkbridge:end
    protected $fillable = ['name', 'email', 'last_seen_at'];
}
PHP;
        file_put_contents($this->tempPath, $original);

        $modifier = new UserModelModifier($this->tempPath);
        $modifier->remove();

        $content = file_get_contents($this->tempPath);
        $this->assertStringNotContainsString('@talkbridge:start', $content);
        $this->assertStringNotContainsString('HasTalkBridgeFeatures', $content);
    }

    public function test_skips_injection_if_already_present(): void
    {
        $original = <<<'PHP'
<?php
namespace App\Models;
class User {
    // @talkbridge:start
    use \RahatulRabbi\TalkBridge\Traits\HasTalkBridgeFeatures;
    // @talkbridge:end
}
PHP;
        file_put_contents($this->tempPath, $original);

        $modifier = new UserModelModifier($this->tempPath);
        $modifier->inject();

        $content = file_get_contents($this->tempPath);
        $this->assertSame(1, substr_count($content, '@talkbridge:start'));
    }
}
