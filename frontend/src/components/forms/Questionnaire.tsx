import type { Instrument } from '@/lib/types';
import { Progress } from '@/components/ui/Display';
import './forms.css';

interface QuestionnaireProps {
  instrument: Instrument;
  answers: Record<number, number>;
  onAnswer: (index: number, value: number) => void;
  highlightMissing?: boolean;
  /** Patient version: friendlier language and no critical item markers. */
  audience?: 'clinician' | 'patient';
}

/**
 * Accessible Likert-style scale: each item is a radio group with its own
 * legend. On mobile the options stack as large buttons.
 */
export function Questionnaire({ instrument, answers, onAnswer, highlightMissing, audience = 'clinician' }: QuestionnaireProps) {
  const answered = Object.keys(answers).length;
  const total = instrument.items.length;

  return (
    <div className="questionnaire">
      <div className="questionnaire__progress" aria-live="polite">
        <div className="split">
          <span className="small soft">
            {answered === total ? 'All questions answered' : `${answered} of ${total} answered`}
          </span>
          <span className="xsmall muted">{instrument.window}</span>
        </div>
        <Progress value={answered} max={total} label="Questionnaire progress" />
      </div>

      <ol className="questionnaire__items">
        {instrument.items.map((item, index) => {
          const missing = highlightMissing && answers[index] === undefined;
          const critical = audience === 'clinician' && instrument.criticalItems.includes(index);
          return (
            <li
              key={index}
              id={`item-${index}`}
              className={['likert', answers[index] !== undefined && 'is-answered', missing && 'is-missing'].filter(Boolean).join(' ')}
            >
              <fieldset>
                <legend className="likert__question">
                  <span className="likert__number">{index + 1}</span>
                  <span>
                    {item}
                    {critical && <span className="likert__critical"> · critical item</span>}
                  </span>
                </legend>
                {missing && <p className="field__error">This question still needs an answer.</p>}
                <div className="likert__options">
                  {instrument.scale.map((option) => (
                    <label key={option.value} className="likert__option">
                      <input
                        type="radio"
                        name={`answer-${index}`}
                        value={option.value}
                        checked={answers[index] === option.value}
                        onChange={() => onAnswer(index, option.value)}
                      />
                      <span>{option.label}</span>
                    </label>
                  ))}
                </div>
              </fieldset>
            </li>
          );
        })}
      </ol>
    </div>
  );
}
