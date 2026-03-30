describe('app.config build variants', () => {
  const loadConfig = (overrides: {
    nativeModules?: Record<string, unknown>;
    platformOverride?: { OS?: string };
  }) => {
    let config: typeof import('../../src/config/app.config');

    jest.isolateModules(() => {
      jest.doMock('react-native', () => ({
        NativeModules: overrides.nativeModules ?? {},
        Platform: {
          OS: overrides.platformOverride?.OS ?? 'ios',
          select: jest.fn(),
        },
      }));

      // eslint-disable-next-line global-require
      config = require('../../src/config/app.config');
    });

    return config!;
  };

  afterEach(() => {
    jest.resetModules();
    jest.clearAllMocks();
    jest.dontMock('react-native');
  });

  it('defaults to student variant when native build variant is missing', () => {
    const config = loadConfig({
      nativeModules: {
        BuildConfigModule: undefined,
        BuildConfig: undefined,
      },
      platformOverride: { OS: 'ios' },
    });

    expect(config.isStudentApp()).toBe(true);
    expect(config.isStaffApp()).toBe(false);
  });

  it('detects staff variant from iOS native module', () => {
    const config = loadConfig({
      nativeModules: {
        BuildConfigModule: { BUILD_VARIANT: 'staff' },
      },
      platformOverride: { OS: 'ios' },
    });

    expect(config.isStaffApp()).toBe(true);
    expect(config.isStudentApp()).toBe(false);
  });

  it('detects student variant on Android by default', () => {
    const config = loadConfig({
      nativeModules: {
        BuildConfig: { BUILD_VARIANT: 'student' },
      },
      platformOverride: { OS: 'android' },
    });

    expect(config.isStudentApp()).toBe(true);
  });
});

