import 'package:audio_service/audio_service.dart';

class LcmAudioHandler extends BaseAudioHandler {
  // We'll use this callback to control the active video player
  // These will be set by the active screen (VideoPlayerScreen or LivestreamScreen)
  Future<void> Function()? onPlayCallback;
  Future<void> Function()? onPauseCallback;
  Future<void> Function(Duration)? onSeekCallback;
  Future<void> Function()? onStopCallback;

  LcmAudioHandler() {
    // Initial state
    playbackState.add(PlaybackState(
      controls: [
        MediaControl.play,
        MediaControl.pause,
        MediaControl.stop,
      ],
      systemActions: const {
        MediaAction.seek,
        MediaAction.playPause,
      },
      androidCompactActionIndices: const [0, 1],
      processingState: AudioProcessingState.idle,
      playing: false,
    ));
  }

  @override
  Future<void> play() async {
    if (onPlayCallback != null) {
      await onPlayCallback!();
    }
  }

  @override
  Future<void> pause() async {
    if (onPauseCallback != null) {
      await onPauseCallback!();
    }
  }

  @override
  Future<void> stop() async {
    if (onStopCallback != null) {
      await onStopCallback!();
    }
    playbackState.add(playbackState.value.copyWith(
      playing: false,
      processingState: AudioProcessingState.idle,
    ));
    super.stop();
  }

  @override
  Future<void> seek(Duration position) async {
    if (onSeekCallback != null) {
      await onSeekCallback!(position);
    }
  }

  // Helper method to update metadata from the UI
  void updateMetadata({
    required String id,
    required String title,
    String? artist,
    String? album,
    String? artUri,
    Duration? duration,
  }) {
    mediaItem.add(MediaItem(
      id: id,
      album: album ?? 'LCMTV',
      title: title,
      artist: artist ?? 'Life Changers Touch',
      artUri: artUri != null ? Uri.parse(artUri) : null,
      duration: duration,
    ));
  }

  // Helper method to update playback state from the UI
  void updatePlaybackState({
    required bool playing,
    AudioProcessingState processingState = AudioProcessingState.ready,
    Duration? position,
    Duration? bufferedPosition,
  }) {
    playbackState.add(playbackState.value.copyWith(
      controls: [
        playing ? MediaControl.pause : MediaControl.play,
        MediaControl.stop,
      ],
      systemActions: const {
        MediaAction.seek,
        MediaAction.playPause,
        MediaAction.stop,
        MediaAction.pause,
        MediaAction.play,
      },
      androidCompactActionIndices: const [0, 1],
      playing: playing,
      processingState: processingState,
      updatePosition: position ?? playbackState.value.position,
      bufferedPosition: bufferedPosition ?? playbackState.value.bufferedPosition,
    ));
  }
}
