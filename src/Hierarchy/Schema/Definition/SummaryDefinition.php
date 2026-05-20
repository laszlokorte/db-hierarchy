<?php

namespace App\Hierarchy\Schema\Definition;

class SummaryDefinition
{
    /**
     * @param array<int,mixed> $segments
     */
    public function __construct(private array $segments)
    {
    }
    /**
     * @return array<int,mixed>
     */
    public function getSegments() : array
    {
        return $this->segments;
    }

    public static function parseSegments(string $string): SummaryDefinition
    {
        $pos = 0;
        $length = strlen($string);
        $result = [];

        while ($pos < $length) {
            $r = preg_match(
                '/(?:\{(?:(?:\$(?<base>self|parent|scope|nesting))(?:(?:\.)(?<basedField>[^\}\{\$][^\}\{]*)|(?:\/)(?<basedType>summary|label|id))?|(?<ownField>[^\}\$\{][^\}\{]*))\}|(?<constant>.+?(?=\{|$)))/i',
                $string,
                $match,
                PREG_UNMATCHED_AS_NULL,
                $pos
            );
            if (!$r) {
                break;
            }
            $pos += strlen($match[0]);

            if ($match['ownField']) {
                $result[] = new SummarySegment(SummarySegment::SLF, SummarySegment::FLD, $match['ownField']);
            } elseif ($match['basedField']) {
                switch ($match['base']) {
                    case 'self':
                        $result[] = new SummarySegment(SummarySegment::SLF, SummarySegment::FLD, $match['basedField']);
                        break;
                    case 'parent':
                        $result[] = new SummarySegment(SummarySegment::PAR, SummarySegment::FLD, $match['basedField']);
                        break;
                    case 'scope':
                        $result[] = new SummarySegment(SummarySegment::SCP, SummarySegment::FLD, $match['basedField']);
                        break;
                    case 'nesting':
                        $result[] = new SummarySegment(SummarySegment::NST, SummarySegment::FLD, $match['basedField']);
                        break;
                    default:
                        throw new \Exception();
                }
            } elseif ($match['basedType']) {
                switch ($match['base']) {
                    case 'self':
                        switch ($match['basedType']) {
                            case 'label':
                                $result[] = new SummarySegment(SummarySegment::SLF, SummarySegment::LBL);
                                break;
                            case 'id':
                                $result[] = new SummarySegment(SummarySegment::SLF, SummarySegment::ID);
                                break;
                            default:
                                throw new \Exception();
                        }
                        break;
                    case 'parent':
                        switch ($match['basedType']) {
                            case 'summary':
                                $result[] = new SummarySegment(SummarySegment::PAR, SummarySegment::SMR);
                                break;
                            case 'label':
                                $result[] = new SummarySegment(SummarySegment::PAR, SummarySegment::LBL);
                                break;
                            case 'id':
                                $result[] = new SummarySegment(SummarySegment::PAR, SummarySegment::ID);
                                break;
                            default:
                                throw new \Exception();
                        }
                        break;
                    case 'scope':
                        switch ($match['basedType']) {
                            case 'summary':
                                $result[] = new SummarySegment(SummarySegment::SCP, SummarySegment::SMR);
                                break;
                            case 'label':
                                $result[] = new SummarySegment(SummarySegment::SCP, SummarySegment::LBL);
                                break;
                            case 'id':
                                $result[] = new SummarySegment(SummarySegment::SCP, SummarySegment::ID);
                                break;
                            default:
                                throw new \Exception();
                        }
                        break;
                    case 'nesting':
                        switch ($match['basedType']) {
                            case 'summary':
                                $result[] = new SummarySegment(SummarySegment::NST, SummarySegment::SMR);
                                break;
                            case 'label':
                                $result[] = new SummarySegment(SummarySegment::NST, SummarySegment::LBL);
                                break;
                            case 'id':
                                $result[] = new SummarySegment(SummarySegment::NST, SummarySegment::ID);
                                break;
                        }
                        break;
                    default:
                        throw new \Exception();
                }
            } elseif ($match['base']) {
                switch ($match['base']) {
                    case 'self':
                        $result[] = new SummarySegment(SummarySegment::SLF, SummarySegment::ID);
                        break;
                    case 'nesting':
                        $result[] = new SummarySegment(SummarySegment::NST, SummarySegment::ID);
                        break;
                    case 'scope':
                        $result[] = new SummarySegment(SummarySegment::SCP, SummarySegment::ID);
                        break;
                    case 'parent':
                        $result[] = new SummarySegment(SummarySegment::PAR, SummarySegment::ID);
                        break;
                    default:
                        throw new \Exception();
                }
            } else {
                $result[] = new SummarySegment(SummarySegment::CONSTANT, $match['constant']);
            }
        }

        return new self($result);
    }

    public function getFieldIds(): array
    {
        return array_map(
            fn ($s) => $s->getFieldId(),
            array_filter($this->segments, fn ($s) => $s->isLocal() && $s->isFieldType())
        );
    }
}
