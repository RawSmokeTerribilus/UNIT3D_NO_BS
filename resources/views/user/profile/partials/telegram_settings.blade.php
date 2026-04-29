<div class="telegram-settings-section" style="border-top: 1px solid #e0e0e0; padding-top: 20px; margin-top: 20px;">
    <h3 class="panel__subheading">{{ __('user.telegram-settings') ?? 'Telegram Settings' }}</h3>
    
    @if ($user->telegram_chat_id)
        <div class="form__group" style="padding: 15px; background-color: #d4edda; border-radius: 4px; border-left: 4px solid #28a745;">
            <p style="margin: 0 0 10px 0; color: #155724;">
                <strong>✓ {{ __('user.telegram-linked') ?? 'Telegram Account Linked' }}</strong>
            </p>
            <p style="margin: 0 0 15px 0; color: #155724; font-size: 0.9em;">
                {{ __('user.telegram-info') ?? 'Your account is connected to our Telegram bot.' }}
            </p>
            <form method="POST" action="{{ route('users.telegram.reset', ['user' => $user->username]) }}" style="display: inline;">
                @csrf
                <button type="submit" 
                        class="form__button form__button--outline" 
                        onclick="return confirm('{{ __('user.telegram-reset-confirm') ?? 'Are you sure you want to reset your token? You will need to link your account again.' }}')"
                        style="padding: 8px 16px; background-color: #fff; border: 1px solid #dc3545; color: #dc3545; border-radius: 4px; cursor: pointer;">
                    {{ __('user.telegram-reset-button') ?? 'Reset Token' }}
                </button>
            </form>
        </div>
    @else
        <div class="form__group">
            <p style="margin: 0 0 15px 0; color: #666;">
                {{ __('user.telegram-not-linked') ?? 'Link your account to Telegram to receive torrent announcements:' }}
            </p>
            
            @if ($user->telegram_token)
                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #dee2e6;">
                    <p style="margin: 0 0 10px 0; font-weight: bold;">
                        {{ __('user.telegram-link-instructions') ?? 'Your Telegram Token:' }}
                    </p>
                    <p style="margin: 0 0 12px 0; font-family: monospace; background-color: #fff; padding: 10px; border-radius: 3px; word-break: break-all; color: #333;">
                        {{ $user->telegram_token }}
                    </p>
                    <p style="margin: 0 0 15px 0; color: #666; font-size: 0.9em;">
                        {{ __('user.telegram-link-command') ?? 'Click the button below or send' }} <code>/start {{ $user->telegram_token }}</code> {{ __('user.telegram-to-bot') ?? 'to our Telegram bot' }}
                    </p>
                    
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="https://t.me/{{ config('services.telegram.bot_username') }}?start={{ $user->telegram_token }}" 
                           class="form__button form__button--filled" 
                           target="_blank"
                           style="display: inline-block; padding: 10px 20px; background-color: #0088cc; color: white; text-decoration: none; border-radius: 4px;">
                            {{ __('user.telegram-link-button') ?? 'Link with Telegram' }}
                        </a>
                        
                        <form method="POST" action="{{ route('users.telegram.reset', ['user' => $user->username]) }}" style="display: inline;">
                            @csrf
                            <button type="submit" 
                                    class="form__button form__button--outline" 
                                    onclick="return confirm('{{ __('user.telegram-reset-confirm') ?? 'Are you sure you want to reset your token? You will need to link your account again.' }}')"
                                    style="padding: 10px 20px; background-color: #fff; border: 1px solid #dc3545; color: #dc3545; border-radius: 4px; cursor: pointer;">
                                {{ __('user.telegram-reset-button') ?? 'Reset Token' }}
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <p style="color: #dc3545; padding: 10px; background-color: #f8d7da; border-radius: 4px;">
                    {{ __('user.telegram-error') ?? 'Error: Could not generate Telegram token. Please contact support.' }}
                </p>
            @endif
        </div>
    @endif
</div>
